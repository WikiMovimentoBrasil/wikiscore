import csv
import requests
from django.db import connection, models
from django.db.models import OuterRef, Subquery
from datetime import datetime
from dateutil import parser
from contests.models import Contest, Edit, Participant, ParticipantEnrollment, Qualification, Evaluation
from django.core.management.base import BaseCommand

class Command(BaseCommand):
    help = "Carrega usuários inscritos no concurso."

    def add_arguments(self, parser):
        parser.add_argument('contest', type=str, help="Nome ID do concurso")

    def handle(self, *args, **options):
        contest_name_id = options.get('contest')
        contest = self.get_contest(contest_name_id)

        # Coleta planilha com usuários inscritos
        if contest.campaign_event_id:
            self.stdout.write("Este concurso possui um evento de campanha.")
            event_id = contest.campaign_event_id
            api = f"https://meta.wikimedia.org/w/rest.php/campaignevents/v0/event_registration/{event_id}/participants"
            response = requests.get(api).json()
            enrollments = self.parse_event(response)
        else:
            csv_content = self.fetch_csv_data(contest)
            enrollments = self.parse_csv(csv_content)
            self.stdout.write(f"Lista de usuários coletada. ({len(enrollments)} usuários encontrados)")

        # Coleta ID da wiki
        wiki_id = self.fetch_wiki_id(contest)
        self.stdout.write(f"Wiki ID: {wiki_id}")

        # Processa os usuários inscritos
        self.process_enrollments(enrollments, contest, wiki_id)

        # Destrava edições bloqueadas
        self.unlock_edits(contest)
        self.stdout.write("Concluído! (2/3)")

    def get_contest(self, contest_name_id):
        """Fetches the contest instance."""
        return Contest.objects.get(name_id=contest_name_id)

    def fetch_csv_data(self, contest):
        """Fetches the CSV data of enrolled users from Outreach."""
        self.stdout.write("Coletando lista de usuários inscritos...")
        csv_params = {"course": contest.outreach_name}
        csv_url = 'https://outreachdashboard.wmflabs.org/course_students_csv'
        response = requests.get(csv_url, params=csv_params, timeout=15)
        response.encoding = 'utf-8'
        if not response.text:
            raise ValueError("Não foi possível encontrar a lista de usuários no Outreach.")
        return response.text

    def parse_event(self, response):
        """Parses the event response into a list of enrollments."""
        enrollments = []
        for participant in response:
            enrollments.append({
                'global_id': participant['user_id'],
                'username': participant['user_name'],
                'enrollment_timestamp': parser.parse(participant['user_registered_at'])
            })
        return enrollments

    def parse_csv(self, csv_content):
        """Parses the CSV content into a list of enrollments."""
        csv_lines = list(csv.reader(csv_content.splitlines()))
        csv_head = csv_lines.pop(0)
        csv_num_rows = len(csv_head)

        enrollments = []
        for csv_line in csv_lines:
            if len(csv_line) != csv_num_rows:
                csv_line = self.fix_csv_line_length(csv_line, csv_num_rows)
            enrollments.append(dict(zip(csv_head, csv_line)))

        return enrollments

    def fix_csv_line_length(self, csv_line, csv_num_rows):
        """Fixes the CSV line length in case of issues."""
        if len(csv_line) < csv_num_rows:
            raise ValueError("Erro! Uma das linhas possui menos colunas que o cabeçalho.")
        extra_columns = len(csv_line) - csv_num_rows + 1
        csv_line_first_column = ",".join(csv_line[:extra_columns])
        return [csv_line_first_column] + csv_line[extra_columns:]

    def fetch_wiki_id(self, contest):
        """Fetches the wiki ID from the contest API."""
        self.stdout.write("Coletando ID da wiki...")
        params = {"action": "query", "format": "json", "meta": "siteinfo"}
        response = requests.get(contest.api_endpoint, params=params).json()
        return response['query']['general']['wikiid']

    def process_enrollments(self, enrollments, contest, wiki_id):
        """Processes each enrollment, updating or inserting users."""
        # Get all participant enrollments for the specific contest
        latest_enrollment_subquery = ParticipantEnrollment.objects.filter(
            contest=contest,
            user=OuterRef('user')
        ).order_by('-when').values('pk')[:1]

        # Get the latest enrollment for each user, then filter by enrolled=True
        already_enrolled = ParticipantEnrollment.objects.filter(
            contest=contest,
            pk__in=Subquery(latest_enrollment_subquery),
            enrolled=True
        ).values_list('user__global_id', flat=True)

        missing_enrollments = [enrollment for enrollment in already_enrolled if enrollment not in enrollments]
        ParticipantEnrollment.objects.bulk_create([
            ParticipantEnrollment(contest=contest, enrolled=False, user=Participant.objects.get(global_id=enrollment, contest=contest)) for enrollment in missing_enrollments
        ])

        for enrollment in enrollments:
            global_id = enrollment['global_id']
            username = enrollment['username']
            timestamp = parser.parse(enrollment['enrollment_timestamp'])
            self.stdout.write(f"Coletando informações do usuário {username} ({global_id})...")

            if not global_id:
                self.stdout.write("Usuário sem ID global. Ignorando...")
                continue

            self.insert_or_update_user(global_id, username, contest, wiki_id, timestamp)

    def insert_or_update_user(self, global_id, username, contest, wiki_id, timestamp):
        """Inserts or updates the user in the Participant table."""
        if Participant.objects.filter(global_id=global_id, contest=contest).exists():
            Participant.objects.filter(global_id=global_id, contest=contest).update(user=username)
            local_id = Participant.objects.get(global_id=global_id, contest=contest).local_id
            self.stdout.write(f"Usuário {username} já está na tabela. Ignorando...")
        else:
            local_id = self.add_user_contest(global_id, contest, wiki_id, timestamp)

        if not local_id:
            self.stdout.write(f"Usuário {username} não encontrado. Ignorando...")
            return None
        else:
            self.stdout.write(f"Usuário {username} inserido com sucesso!")
            self.update_user_edits(local_id, contest, timestamp)

    def add_user_contest(self, global_id, contest, wiki_id, timestamp):
        """Adds a user to the contest."""
        centralauth_response = self.fetch_user_data(global_id, contest)
        centralauth_merged = centralauth_response['query']['globaluserinfo']['merged']
        local_id = next((merged['id'] for merged in centralauth_merged if merged['wiki'] == wiki_id), None)
        user = centralauth_response['query']['globaluserinfo']['name']
        attached = centralauth_response['query']['globaluserinfo']['registration']

        if not local_id:
            return None
        else:
            Participant.objects.create(
                contest=contest,
                user=user,
                timestamp=timestamp,
                global_id=global_id,
                local_id=local_id,
                attached=attached,
            )
            return local_id

    def fetch_user_data(self, global_id, contest):
        """Fetches user data from the contest API."""
        params = {
            "action": "query",
            "format": "json",
            "meta": "globaluserinfo",
            "guiprop": "merged",
            "formatversion": "2",
            "guiid": global_id
        }
        return requests.get(contest.api_endpoint, params=params).json()

    def update_user_edits(self, local_id, contest, timestamp):
        """Updates user edits in the Edit table."""
        self.stdout.write(f"Atualizando edições do usuário com ID local {local_id}...")
        participant = Participant.objects.get(local_id=local_id, contest=contest)

        try:
            already_enrolled = ParticipantEnrollment.objects.filter(contest=contest, user=participant).latest('when').enrolled
        except ParticipantEnrollment.DoesNotExist:
            already_enrolled = False

        if already_enrolled:
            self.stdout.write("Usuário já está inscrito. Ignorando...")
        else:
            ParticipantEnrollment.objects.create(contest=contest, user=participant)
        
        Edit.objects.filter(user_id=local_id, contest=contest, participant=None).update(participant=participant)

        edits = Edit.objects.filter(user_id=local_id, timestamp__gte=timestamp, contest=contest)
        Qualification.objects.bulk_create([
            Qualification(contest=contest, diff=edit) for edit in edits
        ])

    def unlock_edits(self, contest):
        """Unlocks any remaining locked edits in the Edit table."""
        self.stdout.write("Destravando edições...")

        subquery = Evaluation.objects.filter(
            contest=contest,
            edit=OuterRef('edit')
        ).order_by('-when').values('pk')[:1]

        lockeds = Evaluation.objects.filter(
            contest=contest,
            pk__in=Subquery(subquery),
            status__in=['2', '3']
        )

        Evaluation.objects.bulk_create([
            Evaluation(contest=self.contest, edit=locked.edit) for locked in lockeds
        ])
