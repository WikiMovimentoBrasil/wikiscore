import requests
from django.core.exceptions import PermissionDenied
from django.shortcuts import render
from contests.models import Evaluator, Edit, Evaluation, Qualification
from contests.models import Contest


class ModifyHandler():
    def __init__(self, contest):
        self.contest = contest

    def execute(self, request):
        contest = self.contest
        edit = None
        diff = None
        content = None
        author = None
        comment = None
        evaluation = None
        allowed = False
        history_qualifications = None
        history_evaluations = None

        if Evaluator.objects.get(
            contest=contest, 
            profile=request.user.profile
        ).user_status == 'G' or edit.last_evaluation.evaluator.profile == request.user.profile:
            allowed = True        

        if request.method == 'POST' and request.POST.get('diff'):
            diff = request.POST.get('diff')
            edit = Edit.objects.get(contest=contest, diff=diff)

            compare_params = {
                'action': 'compare',
                'prop': 'title|diff|comment|user|ids',
                'format': 'json',
                'fromrev': diff,
                'torelative': 'prev',
            }
            compare = requests.get(contest.api_endpoint, params=compare_params).json().get('compare', {})

            content = compare.get('*', '')
            author = compare.get('touser', '')
            comment = compare.get('tocomment', '')

            history_qualifications = Qualification.objects.filter(
                contest=contest, 
                diff=edit
            ).select_related('evaluator__profile').order_by('-when')

            history_evaluations = Evaluation.objects.filter(
                contest=contest, 
                diff=edit
            ).select_related('evaluator__profile').order_by('-when')
            
            if request.POST.get('obs'):
                if allowed:
                    evaluation = Evaluation.objects.create(
                        contest=contest,
                        evaluator=Evaluator.objects.get(contest=contest, profile=request.user.profile),
                        diff=edit,
                        valid_edit=True if request.POST.get('valid') == '1' else False,
                        pictures=request.POST.get('pic') if request.POST.get('pic').isnumeric() else 0,
                        real_bytes=request.POST.get('overwrite') or Edit.objects.get(contest=contest, diff=diff).orig_bytes,
                        status=1,
                        obs=request.POST.get('obs'),
                    )
                    edit.last_evaluation = evaluation
                    edit.save()
                else:
                    raise PermissionDenied("You are not allowed to perform this action.")
        
        return_dict = {
            'contest': contest, 
            'edit': edit, 
            'diff': diff,
            'evaluation': evaluation,
            'allowed': allowed,
            'content': content,
            'author': author,
            'comment': comment,
            'history_qualifications': history_qualifications,
            'history_evaluations': history_evaluations,
        }
        return return_dict