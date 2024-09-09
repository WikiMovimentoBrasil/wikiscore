from django.urls import path
from .views import (
    contest_view, home_view, triage_view, backtrack_view,
    counter_view, compare_view, edits_view, evaluators_view, 
    modify_view, manage_view, redirect_view
)

urlpatterns = [
    path('', home_view, name='home_view'),
    path('contests/', contest_view, name='contest_view'),
    path('triage/', triage_view, name='triage_view'),
    path('counter/', counter_view, name='counter_view'),
    path('backtrack/', backtrack_view, name='backtrack_view'),
    path('compare/', compare_view, name='compare_view'),
    path('edits/', edits_view, name='edits_view'),
    path('evaluators/', evaluators_view, name='evaluators_view'),
    path('modify/', modify_view, name='modify_view'),
    path('manage/', manage_view, name='manage_view'),
    path('index.php', redirect_view, name='redirect_view'),
]
