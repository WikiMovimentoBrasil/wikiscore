from django.apps import AppConfig


class ContestsConfig(AppConfig):
    default_auto_field = 'django.db.models.BigAutoField'
    name = 'contests'

    def ready(self):
        from .locale import add_custom_languages
        add_custom_languages()