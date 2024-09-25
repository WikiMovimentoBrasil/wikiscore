from django.contrib import admin
from contests.models import Contest, Group, Article, Participant, Edit, Qualification, Evaluation, Evaluator, ParticipantEnrollment

# Register your models here.
admin.site.register(Contest)
admin.site.register(Group)
admin.site.register(Article)
admin.site.register(Participant)
admin.site.register(Edit)
admin.site.register(Qualification)
admin.site.register(Evaluation)
admin.site.register(Evaluator)
admin.site.register(ParticipantEnrollment)