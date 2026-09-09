from django.contrib import admin

from contests.models import (
    Article,
    Contest,
    Edit,
    EditWikidata,
    Evaluation,
    Evaluator,
    Group,
    Participant,
    ParticipantEnrollment,
    Qualification,
)

# Register your models here.
admin.site.register(Contest)
admin.site.register(Group)
admin.site.register(Article)
admin.site.register(Evaluator)


@admin.register(Participant)
class ParticipantAdmin(admin.ModelAdmin):
    raw_id_fields = ('contest', 'last_enrollment')


@admin.register(ParticipantEnrollment)
class ParticipantEnrollmentAdmin(admin.ModelAdmin):
    raw_id_fields = ('contest', 'user')


@admin.register(Qualification)
class QualificationAdmin(admin.ModelAdmin):
    raw_id_fields = ('contest', 'diff', 'evaluator')


@admin.register(Evaluation)
class EvaluationAdmin(admin.ModelAdmin):
    raw_id_fields = ('contest', 'diff', 'evaluator')


@admin.register(EditWikidata)
class EditWikidataAdmin(admin.ModelAdmin):
    raw_id_fields = ('edit',)


@admin.register(Edit)
class EditAdmin(admin.ModelAdmin):
    raw_id_fields = ('article', 'participant', 'last_qualification', 'last_evaluation')