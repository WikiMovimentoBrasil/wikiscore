from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth.decorators import login_required
from django.core.exceptions import PermissionDenied
from django.http import HttpResponse
from django.template import loader
from django.utils import translation
from django.utils.html import escape
from django.db.models.functions import TruncDay
from datetime import timedelta
from functools import wraps
from collections import defaultdict
from .models import Contest, Edit, Participant, Qualification, Evaluator
from .handlers.triage import TriageHandler
from .handlers.contest import ContestHandler
from .handlers.counter import CounterHandler
from .handlers.compare import CompareHandler
from .handlers.evaluators import EvaluatorsHandler
from .handlers.modify import ModifyHandler
from .handlers.manage import ManageHandler
from credentials.models import Profile

def get_contest_from_request(request):
    contest_name_id = request.GET.get('contest')
    if not contest_name_id:
        return redirect('/')
    return get_object_or_404(Contest, name_id=contest_name_id)

def check_evaluator_permission(request, contest):
    try:
        Evaluator.objects.get(contest=contest, profile=request.user.profile, user_status__in=['A', 'G'])
    except Evaluator.DoesNotExist:
        raise PermissionDenied("You are not allowed to access this page.")

def contest_evaluator_required(view_func):
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        contest = get_contest_from_request(request)
        check_evaluator_permission(request, contest)
        return view_func(request, contest, *args, **kwargs)
    return _wrapped_view

def render_with_bidi(request, template_name, context):
    bidi_context = {
        'right': 'left' if translation.get_language_bidi() else 'right',
        'left': 'right' if translation.get_language_bidi() else 'left',
    }
    context.update(bidi_context)
    return render(request, template_name, context)

def home_view(request):
    contests = Contest.objects.all().order_by('-start_time')
    
    contests_chooser = {}
    for contest in contests:
        group = contest.group.name
        if group not in contests_chooser:
            contests_chooser[group] = []
        contests_chooser[group].append([contest.name_id, contest.name])
    contests_groups = list(contests_chooser.keys())

    return render(request, 'home.html', {
        'contests_groups': contests_groups,
        'contests_chooser': contests_chooser,
    })

def contest_view(request):
    contest = get_contest_from_request(request)
    handler = ContestHandler(contest=contest)
    return render_with_bidi(request, 'contest.html', handler.execute(request))

@login_required()
@contest_evaluator_required
def triage_view(request, contest):
    handler = TriageHandler(contest=contest, user=request.user, api_endpoint=contest.api_endpoint)
    if request.method == 'POST':
        do_evaluate = handler.do_evaluate(request)
    else:
        do_evaluate = {'action': None}
    get_evaluate = handler.get_evaluate(request)

    triage_dict = get_evaluate | do_evaluate
    triage_dict.update({
        'triage_points': int(contest.max_bytes_per_article / contest.bytes_per_points),
        'evaluator_status': Evaluator.objects.get(contest=contest, profile=request.user.profile).user_status,
    })
    return render(request, "triage.html", triage_dict)
    return render_with_bidi(request, "triage.html", triage_dict)

@login_required()
@contest_evaluator_required
def counter_view(request, contest):
    handler = CounterHandler(contest=contest)
    return render_with_bidi(request, "counter.html", handler.get_context(request))

@login_required()
@contest_evaluator_required
def backtrack_view(request, contest):
    contest = get_object_or_404(Contest, name_id=request.GET.get('contest'))

    qualified = False
    diff = None
    if request.POST.get('diff'):
        diff = request.POST.get('diff')
        evaluator = Evaluator.objects.get(contest=contest, profile=request.user.profile)
        new_qualification = Qualification.objects.create(contest=contest, diff=Edit.objects.get(diff=diff), evaluator=evaluator)
        qualified = Edit.objects.filter(contest=contest, diff=diff, last_qualification__isnull=True).update(last_qualification=new_qualification)

    edits = Edit.objects.filter(
        contest=contest, 
        last_qualification__isnull=True, 
        participant__isnull=False
    ).order_by('participant__user', 'timestamp')

    result = defaultdict(lambda: {'enrollment_timestamp': None, 'diffs': []})
    for edit in edits:
        user = edit.participant.user
        result[user]['enrollment_timestamp'] = edit.participant.timestamp
        result[user]['diffs'].append({'diff': edit.diff, 'bytes': edit.orig_bytes, 'timestamp': edit.timestamp})

    return render_with_bidi(request, 'backtrack.html', {
        'contest': contest,
        'result': result.items(),
        'diff': diff if qualified else None
    })

@login_required()
@contest_evaluator_required
def compare_view(request, contest):
    handler = CompareHandler(contest=contest)
    return render_with_bidi(request, 'compare.html', handler.execute(request))

@login_required()
@contest_evaluator_required
def edits_view(request, contest):
    edits = Edit.objects.filter(contest=contest).select_related(
        'article', 'participant', 'last_evaluation__evaluator__profile', 'last_qualification'
    )

    if request.POST.get('csv'):
        response = HttpResponse(content_type="text/csv; charset=windows-1252",
                                headers={"Content-Disposition": 'attachment; filename="edits.csv"'})
        response.write(loader.get_template("edits.csv").render({'data': edits}))
        return response

    return render_with_bidi(request, 'edits.html', {'contest': contest, 'edits': edits})

@login_required()
@contest_evaluator_required
def evaluators_view(request, contest):
    handler = EvaluatorsHandler(contest=contest)
    return render_with_bidi(request, 'evaluators.html', handler.execute(request))

@login_required()
@contest_evaluator_required
def modify_view(request, contest):
    handler = ModifyHandler(contest=contest)
    return render_with_bidi(request, 'modify.html', handler.execute(request))

@login_required()
def manage_view(request):
    if request.user.profile.group_set.exists():
        handler = ManageHandler()
        return render_with_bidi(request, 'manage.html', handler.execute(request))