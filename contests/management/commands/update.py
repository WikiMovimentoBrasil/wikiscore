import time
import requests
from datetime import timedelta
from django.core.management import BaseCommand, call_command
from django.db import models
from django.utils import timezone
from contests.models import Contest

class Command(BaseCommand):
    help = 'Update contests'

    def handle(self, *args, **options):
        steps = ["load_edits", "load_users", "load_reverts"]

        contests = Contest.objects.filter(
            start_time__lt=timezone.now()
        ).filter(
            models.Q(started_update__isnull=True) |
            models.Q(started_update__lt=timezone.now() - timedelta(minutes=10))
        ).filter(
            models.Q(next_update__isnull=True) |
            (
                models.Q(end_time__gt=timezone.now() - timedelta(days=2)) &
                models.Q(started_update__lt=models.F('finished_update')) &
                models.Q(next_update__lt=timezone.now())
            )
        )

        if not contests.exists():
            self.stdout.write("Sem atualizações previstas.\n")
            return

        # Loop de concursos
        for contest in contests:
            # Grava horário de início
            contest.started_update = timezone.now()
            contest.save(update_fields=['started_update'])

            # Loop de scripts
            for command_name in steps:
                try:
                    # Executa o comando correspondente
                    self.stdout.write(f"Executando {command_name} para {contest.name_id}...\n")
                    call_command(command_name, contest.name_id)
                    self.stdout.write(f"Executado {command_name} para {contest.name_id}.\n")
                except Exception as e:
                    self.stderr.write(f"Erro ao executar {command_name} para {contest.name_id}: {str(e)}\n")

            # Grava horário de finalização e define o próximo update
            contest.finished_update = timezone.now()
            contest.next_update = timezone.now() + timedelta(days=1)
            contest.save(update_fields=['finished_update', 'next_update'])