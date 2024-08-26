from django.shortcuts import render, redirect, get_object_or_404
from .models import Contest, Edit, Participant, Qualification, Evaluator
from .triage import TriageHandler
from django.db import connection
from datetime import datetime, timedelta
from django.db.models import Count, Sum, Case, When, Value, IntegerField, Q, F, OuterRef, Subquery
from django.db.models.functions import TruncDay
from django.utils import timezone, translation
from django.utils.html import escape
from django.core.exceptions import PermissionDenied
from django.http import HttpResponse
from django.contrib.auth.decorators import login_required
from collections import defaultdict
from functools import wraps

def contest_evaluator_required(view_func):
    @wraps(view_func)
    def _wrapped_view(request, *args, **kwargs):
        contest_name_id = request.GET.get('contest')
        if not contest_name_id:
            return redirect('/')
        contest = get_object_or_404(Contest, name_id=contest_name_id)
        try:
            Evaluator.objects.get(contest=contest, user=request.user)
        except Evaluator.DoesNotExist:
            raise PermissionDenied("You are not allowed to access this page.")
        return view_func(request, *args, **kwargs)
    return _wrapped_view

def color_view(request):
    color = request.GET.get('color')
    
    # Check if the color parameter is provided
    if not color:
        return HttpResponse("/* Color parameter is missing */", content_type="text/css")
    
    # Escape the color parameter to avoid XSS attacks
    color = escape(color)
    
    # Define the CSS content
    css_content = f"""
    .w3-color,
    .w3-hover-color:hover {{
        color: #fff !important;
        background-color: #{color} !important;
    }}

    .w3-text-color,
    .w3-hover-text-color:hover {{
        color: #{color} !important;
    }}

    .w3-border-color,
    .w3-hover-border-color:hover {{
        border-color: #{color} !important;
    }}
    """
    
    # Return the CSS content as a response with the correct content type
    return HttpResponse(css_content, content_type="text/css")

def home_view(request):
    # Get contests from the database
    contests = Contest.objects.all().order_by('-start_time')
    
    contests_chooser = {}
    for contest in contests:
        group = contest.group.name
        if group not in contests_chooser:
            contests_chooser[group] = []
        contests_chooser[group].append([contest.name_id, contest.name])
    contests_groups = list(contests_chooser.keys())
      
    # Render the main template
    return render(request, 'home.html', {
        'contests_groups': contests_groups,
        'contests_chooser': contests_chooser,
    })

def contest_view(request):
    contest_name_id = request.GET.get('contest')
    if not contest_name_id:
        return redirect('/')
    contest = get_object_or_404(Contest, name_id=contest_name_id)

    is_evaluator = False
    try:
        Evaluator.objects.get(contest=contest, user=request.user)
        is_evaluator = True
    except Evaluator.DoesNotExist:
        pass

    date_min = contest.start_time
    date_max = contest.end_time
    date_range = date_range = [date_min + timedelta(days=x) for x in range((date_max - date_min).days + 1)]

    subquery = Qualification.objects.filter(
        contest=contest,
        diff=OuterRef('diff')
    ).order_by('-when').values('pk')[:1]
    approved_edits = Qualification.objects.filter(
        contest=contest,
        pk__in=Subquery(subquery),
        status=1
    ).values_list('diff__diff', flat=True)

    new_articles = {
        entry['date']: entry['count'] 
        for entry in Edit.objects
            .filter(contest=contest, new_page=True, timestamp__range=(date_min, date_max))
            .annotate(date=TruncDay('timestamp'))
            .values('date')
            .annotate(count=Count('id'))
            .order_by('date')
    }
    new_participants = {
        entry['date']: entry['count'] 
        for entry in Participant.objects
            .filter(contest=contest, timestamp__range=(date_min, date_max))
            .annotate(date=TruncDay('timestamp'))
            .values('date')
            .annotate(count=Count('id'))
            .order_by('date')
    }
    total_edits = {
        entry['date']: entry['count'] 
        for entry in Edit.objects
            .filter(contest=contest, timestamp__range=(date_min, date_max))
            .annotate(date=TruncDay('timestamp'))
            .values('date')
            .annotate(count=Count('id'))
            .order_by('date')
    }
    total_bytes = {
        entry['date']: entry['sum_bytes'] 
        for entry in Edit.objects
            .filter(contest=contest, orig_bytes__gte=0, timestamp__range=(date_min, date_max))
            .annotate(date=TruncDay('timestamp'))
            .values('date')
            .annotate(sum_bytes=Sum('orig_bytes'))
            .order_by('date')
    }
    valid_edits = {
        entry['date']: entry['count'] 
        for entry in Edit.objects
            .filter(contest=contest, pk__in=approved_edits, timestamp__range=(date_min, date_max))
            .annotate(date=TruncDay('timestamp'))
            .values('date')
            .annotate(count=Count('id'))
            .order_by('date')
    }
    valid_bytes = {
        entry['date']: entry['sum_bytes'] 
        for entry in Edit.objects
            .filter(contest=contest, pk__in=approved_edits, timestamp__range=(date_min, date_max))
            .annotate(date=TruncDay('timestamp'))
            .values('date')
            .annotate(sum_bytes=Sum('orig_bytes'))
            .order_by('date')
    }
    
    # Prepare the result
    dates = []
    new_articles_list = []
    new_participants_list = []
    total_edits_list = []
    total_bytes_list = []
    valid_edits_list = []
    valid_bytes_list = []

    for date in date_range:
        dates.append(str(abs(date - date_min).days))
        new_articles_list.append(str(new_articles.get(date, 0)))
        new_participants_list.append(str(new_participants.get(date, 0)))
        total_edits_list.append(str(total_edits.get(date, 0)))
        total_bytes_list.append(str(total_bytes.get(date, 0)))
        valid_edits_list.append(str(valid_edits.get(date, 0)))
        valid_bytes_list.append(str(valid_bytes.get(date, 0)))

    result = {
        'date': ', '.join(dates),
        'new_articles': ', '.join(new_articles_list),
        'new_participants': ', '.join(new_participants_list),
        'total_edits': ', '.join(total_edits_list),
        'total_bytes': ', '.join(total_bytes_list),
        'valid_edits': ', '.join(valid_edits_list),
        'valid_bytes': ', '.join(valid_bytes_list),
        'is_evaluator': is_evaluator,
    }

    return render(request, 'contest.html', {'contest': contest, 'result': result})

@login_required()
@contest_evaluator_required
def triage_view(request):
    contest = get_object_or_404(Contest, name_id=request.GET.get('contest'))

    handler = TriageHandler(contest=contest, user=request.user, api_endpoint=contest.api_endpoint)
    if request.method == 'POST':
        do_evaluate = handler.do_evaluate(request)
    else:
        do_evaluate = {'action': None}
    get_evaluate = handler.get_evaluate(request)

    triage_dict = get_evaluate | do_evaluate
    triage_dict.update({
        'triage_points': int(contest.max_bytes_per_article / contest.bytes_per_points),
        'evaluator_status': Evaluator.objects.get(contest=contest, user=request.user).user_status,
        'right': 'left' if translation.get_language_bidi() else 'right',
        'left': 'right' if translation.get_language_bidi() else 'left',
    })
    return render(request, "triage.html", triage_dict)

@login_required()
@contest_evaluator_required
def backtrack_view(request):
    contest = get_object_or_404(Contest, name_id=request.GET.get('contest'))

    qualified = False
    diff = None
    if request.POST.get('diff'):
        diff = request.POST.get('diff')
        edit = Edit.objects.get(diff=diff)
        evaluator = Evaluator.objects.get(contest=contest, user=request.user)
        new_qualification = Qualification.objects.create(contest=contest, diff=edit, evaluator=evaluator)
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

    return render(request, 'backtrack.html', {
        'contest': contest,
        'result': result.items(),
        'diff': diff if qualified else None,
        'right': 'left' if translation.get_language_bidi() else 'right',
    })

