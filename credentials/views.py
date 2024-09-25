from django.shortcuts import render, reverse, redirect
from django.contrib.auth.decorators import login_required
from django.contrib.auth import logout as auth_logout

# Create your views here.
@login_required()
def triage_view(request):
    context = {}
    return render(request, "triage.html", context)

def login_oauth():
    return redirect(reverse('social:begin', kwargs={"backend": "mediawiki"}))

def logout(request):
    auth_logout(request)
    return redirect(reverse('home_view'))