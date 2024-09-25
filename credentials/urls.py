from django.urls import path, include
from . import views

urlpatterns = [
    path("login/", views.login_oauth, name="login"),
    path("oauth/", include("social_django.urls", namespace="social_django")),
    path("logout/", views.logout, name="logout"),
]
