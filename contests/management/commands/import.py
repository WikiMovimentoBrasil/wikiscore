from django.core.management.base import BaseCommand
from django.db import connections
from contests.models import Contest, Group, Evaluator, Article, Participant, ParticipantEnrollment, Qualification, Edit, Evaluation
from credentials.models import Profile
import random
from django.utils.timezone import make_aware

class Command(BaseCommand):
    help = 'Import data from the old database'

    def handle(self, *args, **options):
        self.stdout.write("Iniciando a importação de dados...")

        with connections['old_db'].cursor() as cursor:
            cursor.execute('SELECT * FROM manage__contests')
            columns = cursor.description 
            contests = [{columns[index][0]:column for index, column in enumerate(value)} for value in cursor.fetchall()]

            for contest in contests:
                self.stdout.write(f"Importando o concurso: {contest.get('name_id')}")

                group, _ = Group.objects.get_or_create(name=contest.get('group', None))
                try:
                    Contest.objects.get(name_id=contest.get('name_id', None)).delete()
                    self.stdout.write(f"Concurso existente deletado: {contest.get('name_id')}")
                except Contest.DoesNotExist:
                    pass
                
                contest_instance = Contest.objects.create(
                    name_id=contest.get('name_id', None),
                    start_time=make_aware(contest.get('start_time', None)),
                    end_time=make_aware(contest.get('end_time', None)),
                    name=contest.get('name', None),
                    group=group,
                    revert_time=contest.get('revert_time', None),
                    official_list_pageid=contest.get('official_list_pageid', None),
                    category_pageid=contest.get('category_pageid', None),
                    category_petscan=contest.get('category_petscan', None),
                    endpoint=contest.get('endpoint', None),
                    api_endpoint=contest.get('api_endpoint', None),
                    outreach_name=contest.get('outreach_name', None),
                    campaign_event_id=None,
                    bytes_per_points=contest.get('bytes_per_points', None),
                    max_bytes_per_article=contest.get('max_bytes_per_article', None),
                    minimum_bytes=contest.get('minimum_bytes', None),
                    pictures_per_points=contest.get('pictures_per_points', None),
                    pictures_mode=contest.get('pictures_mode', None),
                    max_pic_per_article=contest.get('max_pic_per_article', None),
                    theme=contest.get('theme', None),
                    color=contest.get('color', '') if contest.get('color', None) else '',
                )
                self.stdout.write(f"Concurso importado: {contest_instance.name_id}")

                # Importando os artigos
                self.stdout.write(f"Importando artigos do concurso: {contest_instance.name_id}")
                cursor.execute('SELECT * FROM ' + contest_instance.name_id + '__articles')
                columns = cursor.description 
                articles = [{columns[index][0]:column for index, column in enumerate(value)} for value in cursor.fetchall()]
                for article in articles:
                    Article.objects.create(
                        contest=contest_instance,
                        articleID=article.get('articleID', None),
                        title=article.get('title', None),
                    )
                self.stdout.write(f"Artigos importados para o concurso: {contest_instance.name_id}")

                # Importando os usuários
                self.stdout.write(f"Importando participantes do concurso: {contest_instance.name_id}")
                cursor.execute('SELECT * FROM ' + contest_instance.name_id + '__users')
                columns = cursor.description
                users = [{columns[index][0]:column for index, column in enumerate(value)} for value in cursor.fetchall()]
                for user in users:
                    participant = Participant.objects.create(
                        contest=contest_instance,
                        user=user.get('user', None),
                        timestamp=make_aware(user.get('timestamp', None)),
                        global_id=user.get('global_id', None),
                        local_id=user.get('local_id', None),
                        attached=make_aware(user.get('attached')) if user.get('attached', None) else None,
                        last_enrollment=None,
                    )
                    enrollment = ParticipantEnrollment.objects.create(
                        contest=contest_instance,
                        user=participant,
                        enrolled=True,
                    )
                    participant.last_enrollment = enrollment
                    participant.save()
                self.stdout.write(f"Participantes importados para o concurso: {contest_instance.name_id}")

                # Importando os avaliadores
                self.stdout.write(f"Importando avaliadores do concurso: {contest_instance.name_id}")
                cursor.execute('SELECT * FROM ' + contest_instance.name_id + '__credentials')
                columns = cursor.description
                credentials = [{columns[index][0]:column for index, column in enumerate(value)} for value in cursor.fetchall()]
                for credential in credentials:
                    try:
                        profile = Profile.objects.get(username=credential.get('user_name', None))
                    except Profile.DoesNotExist:
                        while True:
                            mock_global_id = f'A{random.randint(100000000, 999999999)}'
                            if not Profile.objects.filter(global_id=mock_global_id).exists():
                                break

                        profile = Profile.objects.create(
                            global_id=mock_global_id,
                            username=credential.get('user_name', None),
                            account=None,
                        )

                    Evaluator.objects.create(
                        profile=profile,
                        contest=contest_instance,
                        user_status=credential.get('user_status', None),
                    )
                self.stdout.write(f"Avaliadores importados para o concurso: {contest_instance.name_id}")

                # Importando os edits
                self.stdout.write(f"Importando edições do concurso: {contest_instance.name_id}")
                cursor.execute('SELECT * FROM ' + contest_instance.name_id + '__edits')
                columns = cursor.description
                edits = [{columns[index][0]:column for index, column in enumerate(value)} for value in cursor.fetchall()]
                
                done = 0
                
                for edit in edits:
                    try:
                        article = Article.objects.get(contest=contest_instance, articleID=edit.get('article', None))
                    except Article.DoesNotExist:
                        article = Article.objects.create(
                            contest=contest_instance,
                            articleID=edit.get('article', None),
                            title='Title not found',
                        )

                    try:
                        user = Participant.objects.get(contest=contest_instance, local_id=edit.get('user_id', None))
                    except Participant.DoesNotExist:
                        user = None

                    if edit.get('timestamp', None) is None:
                        continue

                    diff = Edit.objects.create(
                        contest=contest_instance,
                        diff=edit.get('diff', None),
                        article=article,
                        timestamp=make_aware(edit.get('timestamp', None)),
                        user_id=edit.get('user_id', None),
                        participant=user,
                        orig_bytes=edit.get('orig_bytes') if edit.get('orig_bytes', None) else edit.get('bytes', None),
                        new_page=True if edit.get('new_page', None) == 1 else False,
                        last_qualification=None,
                        last_evaluation=None,
                    )
                    if user is not None:
                        qual = Qualification.objects.create(
                            contest=contest_instance,
                            diff=diff,
                            evaluator=None,
                            status=1 if edit.get('reverted', None) is None else 0,
                        )
                        diff.last_qualification = qual

                        by_value = edit.get('by', None)
                        if by_value is not None and not by_value.startswith(('hold-', 'skip-')):
                            evaluator = Evaluator.objects.get(
                                profile__username=by_value,
                                contest=contest_instance
                            )
                            evaluation = Evaluation.objects.create(
                                contest=contest_instance,
                                evaluator=evaluator,
                                diff=diff,
                                valid_edit=True if edit.get('valid_edit', None) == 1 else False,
                                pictures=edit.get('pictures', None),
                                real_bytes=edit.get('bytes', None),
                                status=1,
                                obs=edit.get('obs', None),
                            )
                            diff.last_evaluation = evaluation
                        
                        diff.save()

                    done += 1
                    if done % 100 == 0:
                        self.stdout.write(f"{done} edições processadas...")

                self.stdout.write(f"Edições importadas para o concurso: {contest_instance.name_id}")
        
        self.stdout.write("Importação de dados concluída.")
