import requests
import time
from datetime import datetime
from django.core.management.base import BaseCommand
from django.db import connection
from contests.models import Contest, Article, Edit, Qualification
from django.db.models import OuterRef, Subquery

class Command(BaseCommand):
    help = 'Update reverts'

    def add_arguments(self, parser):
        parser.add_argument('contest', type=str, help="Nome ID do concurso")

    def handle(self, *args, **options):
        contest_name_id = options.get('contest')
        contest = Contest.objects.get(name_id=contest_name_id)
        
        # Coleta lista de edições
        subquery = Qualification.objects.filter(
            contest=contest,
            diff=OuterRef('diff')
        ).order_by('-when').values('pk')[:1]

        # Get the latest enrollment for each user, then filter by enrolled=True
        already_reverted = Qualification.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status=0
        ).values_list('diff__diff', flat=True)
        edits = Edit.objects.filter(contest=contest).exclude(participant=None).values_list('diff', flat=True)

        diffs = list(set(edits) - set(already_reverted))

        # Loop para análise de cada edição
        for diff in diffs:

            # Coleta tags da revisão
            revisions_api_params = {
                "action": "query",
                "format": "json",
                "prop": "revisions",
                "rvprop": "sha1|tags",
                "revids": diff,
            }
            revisions_api = requests.get(contest.api_endpoint, params=revisions_api_params).json()
            revisions_api_query = revisions_api.get('query', {})
            revision = None

            if 'pages' in revisions_api_query:
                self.stdout.write(f"Revisão: {diff}")
                revision_page = next(iter(revisions_api_query['pages'].values()), None)
                revision = revision_page['revisions'][0] if revision_page and 'revisions' in revision_page else None

            # Marca edição caso tenha sido revertida ou eliminada
            if (
                'badrevids' in revisions_api_query
                or (revision and 'sha1hidden' in revision)
                or (revision and 'mw-reverted' in revision['tags'])
            ):
                Qualification.objects.create(
                    contest=contest,
                    diff=Edit.objects.get(diff=diff),
                    status=0,
                )
                self.stdout.write(f"Marcada edição {diff} como revertida.")

        # Encerra script
        self.stdout.write("Concluído! (3/3)")
