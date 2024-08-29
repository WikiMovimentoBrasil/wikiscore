from django.contrib import admin
from credentials.models import CustomUser, Profile

# Register your models here.
class AccountUserAdmin(admin.ModelAdmin):
    list_display = ('username', 'email', 'is_staff', 'is_active', 'date_joined')
    search_fields = ('username', 'email')
    readonly_fields = ('date_joined',)

admin.site.register(CustomUser, AccountUserAdmin)
admin.site.register(Profile)