import requests
from credentials.models import Profile
from django.db.models import Count, Q
from django.utils import translation
from contests.models import Evaluator, Edit

class EvaluatorsHandler:
    def __init__(self, contest):
        self.contest = contest

    def execute(self, request):
        contest = self.contest
        if request.method == 'POST' and Evaluator.objects.get(contest=contest, profile=request.user.profile).user_status == 'G':
            if request.POST.get('new'):
                username = request.POST.get('new')
                try:
                    profile = Profile.objects.get(username=username)
                except Profile.DoesNotExist:
                    api_params = {
                        'action': 'query',
                        'meta': 'globaluserinfo',
                        'guiuser': username,
                        'format': 'json',
                    }
                    response = requests.get(contest.api_endpoint, params=api_params)
                    data = response.json()

                    if 'query' in data and 'globaluserinfo' in data['query'] and 'id' in data['query']['globaluserinfo']:
                        global_id = data['query']['globaluserinfo']['id']
                        profile = Profile.objects.create(global_id=global_id, username=username)
                        Evaluator.objects.create(contest=contest, profile=profile, user_status='A')

                else:
                    Evaluator.objects.create(contest=contest, profile=profile, user_status='A')
                
            if request.POST.get('user'):
                if request.POST.get('off'):
                    Evaluator.objects.filter(
                        contest=contest,
                        profile__username=request.POST.get('user'),
                    ).update(user_status='P')

                if request.POST.get('on'):
                    Evaluator.objects.filter(
                        contest=contest,
                        profile__username=request.POST.get('user'),
                    ).update(user_status='A')

                if request.POST.get('reset'):
                    Edit.objects.filter(
                        contest=contest,
                        last_evaluation__evaluator__profile__username=request.POST.get('user'),
                    ).update(last_evaluation=None)

        evaluators = Profile.objects.filter(
                evaluator__contest=contest,
                evaluator__user_status='A',
            ).annotate(
                evaluation_count=Count('evaluator__evaluation'),
                filter=Q(evaluator__evaluation__status='1')
            ).values('global_id', 'username', 'evaluation_count')
        managers = Profile.objects.filter(
                evaluator__contest=contest,
                evaluator__user_status='G',
            ).annotate(
                evaluation_count=Count('evaluator__evaluation'),
                filter=Q(evaluator__evaluation__status='1')
            ).values('global_id', 'username', 'evaluation_count')
        disabled = Profile.objects.filter(
                Q(evaluator__contest=contest, evaluator__user_status='P') |
                ~Q(evaluator__contest=contest)
            ).annotate(
                evaluation_count=Count('evaluator__evaluation'),
                filter=Q(evaluator__evaluation__status='1')
            ).values('global_id', 'username', 'evaluation_count')

        return_dict = {
            'contest': contest,
            'evaluators': evaluators,
            'managers': managers,
            'disabled': disabled,
            'status': Evaluator.objects.get(contest=contest, profile=request.user.profile).user_status,
            'right': 'left' if translation.get_language_bidi() else 'right',
            'left': 'right' if translation.get_language_bidi() else 'left',
        }

        return return_dict