import requests
from datetime import datetime, timedelta
from django.shortcuts import render, redirect, get_object_or_404
from .models import Contest, Edit, Evaluation, Participant, ParticipantEnrollment, Qualification, Evaluator
from django.utils import timezone
from django.db.models import Sum, Case, When, Value, IntegerField, Q, OuterRef, Subquery


class TriageHandler:
    def __init__(self, contest, user, api_endpoint):
        self.contest = contest
        self.user = user
        self.api_endpoint = api_endpoint

    def do_evaluate(self, request):
        if request.POST.get('skip'):
            diff = request.POST.get('diff') or None
            if not diff:
                raise ValueError('Edit not found')
            else:
                if self.skip_edit(diff):
                    return {'action': 'skip'}

        elif request.POST.get('release'):
            if self.release_edit():
                return {'action': 'release'}

        elif request.POST.get('unhold'):
            if Evaluator.objects.get(contest=contest, user=self.user).user_status != 'G':
                raise PermissionError('User is not a group member')
            else:
                if self.unhold_edit():
                    return {'action': 'unhold'}

        else:
            return self.evaluate_edit(request)


    def get_evaluate(self, request):
        # Fetch the contest data
        contest = self.contest  
        return_dict = {'contest': contest }
          
        # Check if the update start time is greater than the end time (indicating an update is in progress)
        if contest.start_time > contest.end_time:
            return_dict.update({'error': 'updating'})
        
        # Fetch the next available edit for triage
        next_edit = self.get_next_edit(contest)
        if not next_edit:
            return_dict.update({'error': 'noedit'})
        else:
            # Mark the edit as being held by the current user
            self.hold_edit(next_edit, request.user)
            
            # Fetch comparison data for the edit
            compare_data, compare_data_mobile = self.fetch_compare_data(contest.api_endpoint, next_edit.diff)
            if not compare_data:
                self.mark_edit_reverted(next_edit)
                return_dict.update({'error': 'reverted'})
            else:
                # Fetch the revision history of the article related to the edit
                revision_history = self.fetch_revision_history(contest.api_endpoint, next_edit.article.articleID, contest.start_time)
                last_revision = revision_history[-1] if revision_history else None

                # Validate the parent ID of the last (oldest) revision
                if last_revision and not self.is_valid_parentid(last_revision['parentid']):
                    raise ValueError('Parent ID is not a number')

                # Update the revision history with data from the previous revision
                self.update_history_with_previous_revision(contest.api_endpoint, revision_history, last_revision)

                # Build the context for rendering the template
                render_context = self.build_context(revision_history, next_edit.diff)

                return_dict.update({
                    'context': render_context,
                    'edit': next_edit,
                    'compare': compare_data,
                    'compare_html': compare_data['*'],
                    'compare_mobile': compare_data_mobile,
                    'compare_mobile_html': compare_data_mobile['*'],
                    'history': revision_history,
                })

        # Calculate edit statistics (e.g., on queue, on hold)
        edit_stats = self.calculate_edit_stats(contest)
        return_dict.update({
            'onqueue': edit_stats['onqueue'],
            'onwait': edit_stats['onwait'],
            'onskip': edit_stats['onskip'],
            'onhold': edit_stats['onhold'],
            'onpending': edit_stats['onhold'] + edit_stats['onskip'],
        })
        
        # Return the data for rendering the template
        return return_dict 

    def skip_edit(self, diff):
        return Evaluation.objects.create(
            contest=self.contest,
            evaluator=Evaluator.objects.get(contest=self.contest, user=self.user),
            diff=Edit.objects.get(diff=diff),
            status='3'
        )

    def release_edit(self):
        evaluator = Evaluator.objects.get(contest=self.contest, user=self.user)

        subquery = Evaluation.objects.filter(
            contest=self.contest,
            evaluator=evaluator,
            diff=OuterRef('diff')
        ).order_by('-when').values('pk')[:1]

        skipped = Evaluation.objects.filter(
            contest=self.contest,
            pk__in=Subquery(subquery),
            status='3'
        )

        return Evaluation.objects.bulk_create([
            Evaluation(contest=self.contest, evaluator=evaluator, diff=skip.edit) for skip in skipped
        ])

    def evaluate_edit(self, request):

        diff = request.POST.get('diff') or None
        if not diff:
            raise ValueError('Edit not found')

        if self.contest.pictures_mode == 2 and isnumeric(request.POST.get('picture')):
            picture = request.POST.get('picture')
        else:
            picture = True if request.POST.get('picture') == 'sim' else False

        overwrite_value = request.POST.get('overwrite')
        real_bytes = int(overwrite_value) if overwrite_value and overwrite_value.isnumeric() else Edit.objects.get(diff=diff).orig_bytes

        evaluation = Evaluation.objects.create(
            contest=self.contest,
            evaluator=Evaluator.objects.get(contest=self.contest, user=self.user),
            diff=Edit.objects.get(diff=request.POST.get('diff')),
            valid_edit=True if request.POST.get('valid') == 'sim' else False,
            pictures=picture,
            real_bytes=real_bytes,
            status='1',
            obs=request.POST.get('obs') or None
        )
        return evaluation.__dict__

    def get_next_edit(self, contest):

        subquery = Evaluation.objects.filter(
            contest=contest,
            diff=OuterRef('diff')
        ).order_by('-when').values('pk')[:1]

        evaluated = Evaluation.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status__in=['1', '3']
        ).values_list('diff', flat=True)

        held = Evaluation.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status='2'
        ).exclude(
            evaluator=Evaluator.objects.get(contest=contest, user=self.user)
        ).values_list('diff', flat=True)

        active = Qualification.objects.filter(
            contest=contest,
            status=1,
        ).values_list('diff_id', flat=True)

        edit = Edit.objects.filter(
            pk__in=active,
            contest=contest,
            orig_bytes__gte=contest.minimum_bytes or 1,
            timestamp__lte=timezone.now() - timedelta(hours=contest.revert_time),
            participant__isnull=False,
        ).exclude(pk__in=evaluated).exclude(pk__in=held).order_by('timestamp').first()

        return edit

    def unhold_edit(self):
        subquery = Evaluation.objects.filter(
            contest=contest,
            diff=OuterRef('diff')
        ).order_by('-when').values('pk')[:1]

        lockeds = Evaluation.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status__in=['2', '3']
        )

        return Evaluation.objects.bulk_create([
            Evaluation(contest=self.contest, diff=locked.diff) for locked in lockeds
        ])

    # Function to mark the edit as being held by the user
    def hold_edit(self, edit, user):
        subquery = Evaluation.objects.filter(
            contest=self.contest,
            diff=OuterRef('diff')
        ).order_by('-when').values('pk')[:1]

        held = Evaluation.objects.filter(
            contest=self.contest,
            pk__in=Subquery(subquery),
            status=2
        )

        if not held:
            Evaluation.objects.create(
                contest=self.contest,
                evaluator=Evaluator.objects.get(contest=self.contest, user=user),
                diff=edit,
                status='2' # Status '2' indicates the edit is on hold
            )

    # Function to fetch comparison data for the edit
    def fetch_compare_data(self, api_endpoint, diff):
        compare_params = {
            'action': 'compare',
            'prop': 'title|diff|comment|user|ids',
            'format': 'json',
            'fromrev': diff,
            'torelative': 'prev',
        }
        compare = requests.get(api_endpoint, params=compare_params).json().get('compare', {})
        
        compare_params['difftype'] = 'inline'
        compare_mobile = requests.get(api_endpoint, params=compare_params).json().get('compare', {})
        
        return compare, compare_mobile

    # Function to mark the edit as reverted
    def mark_edit_reverted(self, edit):
        edit.reverted = True
        edit.save()

    # Function to fetch the revision history of the article related to the edit
    def fetch_revision_history(self, api_endpoint, article_id, start_time):
        history_params = {
            'action': 'query',
            'format': 'json',
            'prop': 'revisions',
            'pageids': article_id,
            'rvprop': 'timestamp|user|size|ids',
            'rvlimit': 'max',
            'rvend': start_time.strftime('%Y-%m-%dT%H:%M:%S.000Z'),
        }
        return requests.get(api_endpoint, params=history_params).json().get('query', {}).get('pages', {}).get(str(article_id), {}).get('revisions', [])

    # Function to validate the parent ID of the last revision
    def is_valid_parentid(self, parentid):
        return isinstance(parentid, int) and parentid >= 0

    # Function to update the revision history with data from the previous revision
    def update_history_with_previous_revision(self, api_endpoint, revision_history, last_revision):
        if last_revision and last_revision['parentid'] != 0:
            previous_revision_params = {
                'action': 'compare',
                'format': 'json',
                'fromrev': last_revision['revid'],
                'torelative': 'prev',
                'prop': 'size',
            }
            previous_revision = requests.get(api_endpoint, params=previous_revision_params).json().get('compare', {})
            revision_history.append({
                'size': previous_revision.get('fromsize', 0),
                'timestamp': '1970-01-01T00:00:00',
                'user': 'None',
                'revid': 0,
            })

    # Function to build the context for rendering the template
    def build_context(self, revision_history, current_diff):
        context = []
        for i, edit in enumerate(revision_history):
            if i + 1 < len(revision_history):
                delta = edit['size'] - revision_history[i + 1]['size']
            else:
                delta = edit['size']
            delta_color = 'green' if delta > 0 else ('red' if delta < 0 else 'grey')
            history_class = 'w3-small w3-leftbar w3-border-grey w3-padding-small' if edit['revid'] == current_diff else 'w3-small'
            
            if edit['revid'] != 0:
                context.append({
                    'class': history_class,
                    'user': edit['user'],
                    'timestamp': edit['timestamp'],
                    'color': delta_color,
                    'bytes': delta,
                    'revid': edit['revid'],
                })
        return context

    # Function to calculate edit statistics (e.g., on queue, on hold)
    def calculate_edit_stats(self, contest):

        subquery = Evaluation.objects.filter(
            contest=contest,
            diff=OuterRef('diff')
        ).order_by('-when').values('pk')[:1]

        evaluated = Evaluation.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status='1'
        ).values_list('diff', flat=True)

        held = Evaluation.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status='2'
        ).values_list('diff', flat=True)

        skipped = Evaluation.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status='3'
        ).values_list('diff', flat=True)

        active = Qualification.objects.filter(
            contest=contest,
            status=1,
        ).values_list('diff_id', flat=True)

        wait = Edit.objects.filter(
            pk__in=active,
            contest=contest,
            orig_bytes__gte=contest.minimum_bytes or 1,
            timestamp__gte=timezone.now() - timedelta(hours=contest.revert_time),
            participant__isnull=False,
        ).exclude(pk__in=evaluated).exclude(pk__in=held).exclude(pk__in=skipped)

        queue = Edit.objects.filter(
            pk__in=active,
            contest=contest,
            orig_bytes__gte=contest.minimum_bytes or 1,
            timestamp__lte=timezone.now() - timedelta(hours=contest.revert_time),
            participant__isnull=False,
        ).exclude(pk__in=evaluated).exclude(pk__in=held).exclude(pk__in=skipped)


        onwait = wait.count()
        onskip = Edit.objects.filter(pk__in=skipped).count()
        onhold = Edit.objects.filter(pk__in=held).count()
        onqueue = queue.count()

        return {'onqueue': onqueue, 'onwait': onwait, 'onskip': onskip, 'onhold': onhold}
