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
admin.site.register(Participant)
admin.site.register(EditWikidata)
admin.site.register(Qualification)
admin.site.register(Evaluation)
admin.site.register(Evaluator)
admin.site.register(ParticipantEnrollment)

@admin.register(Edit)
class EditAdmin(admin.ModelAdmin):
    raw_id_fields = ('article', 'participant', 'last_qualification', 'last_evaluation')