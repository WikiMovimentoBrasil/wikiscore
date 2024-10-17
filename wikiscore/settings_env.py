import os
from pathlib import Path
from dotenv import load_dotenv

load_dotenv()

BASE_DIR = Path(__file__).resolve().parent.parent
HOME = os.environ.get('HOME') or ""
SECRET_KEY = os.environ.get("SECRET_KEY")
SOCIAL_AUTH_MEDIAWIKI_URL = 'https://meta.wikimedia.org/w/index.php'
SOCIAL_AUTH_MEDIAWIKI_KEY = os.environ.get("SOCIAL_AUTH_MEDIAWIKI_KEY")
SOCIAL_AUTH_MEDIAWIKI_SECRET = os.environ.get("SOCIAL_AUTH_MEDIAWIKI_SECRET")

def configure_settings():
    if os.path.exists(HOME + '/replica.my.cnf'):
        debug = False
        hosts = [ os.environ.get("TOOLNAME") + '.toolforge.org', 'toolforge.org' ]
        callback = 'https://' + os.environ.get("TOOLNAME") + '.toolforge.org/oauth/complete/mediawiki/'
        message = 'You are running in production mode'
        email_host = 'mail.tools.wmflabs.org'
        email_port = 587
        server_email = os.environ.get("TOOLNAME") + '@tools.wmflabs.org'
        admins = [('Admin', 'wikiscore@wmnobrasil.org')]

        databases = {
            'default': {
                'ENGINE': 'django.db.backends.mysql',
                'NAME': os.environ.get("TOOL_TOOLSDB_USER") + '__wikiscore', # type: ignore
                'USER': os.environ.get("TOOL_TOOLSDB_USER"),
                'PASSWORD': os.environ.get("TOOL_TOOLSDB_PASSWORD"),
                'HOST': 'tools.db.svc.wikimedia.cloud',
                'PORT': '',
            },
            'old_db': {
                'ENGINE': 'django.db.backends.mysql',
                'NAME': os.environ.get("TOOL_TOOLSDB_USER") + '__wikiconcursos', # type: ignore
                'USER': os.environ.get("TOOL_TOOLSDB_USER"),
                'PASSWORD': os.environ.get("TOOL_TOOLSDB_PASSWORD"),
                'HOST': 'tools.db.svc.wikimedia.cloud',
                'PORT': '',
            }
        }

    else:
        debug = True
        hosts = ['127.0.0.1']
        callback = 'http://127.0.0.1:8000/oauth/complete/mediawiki/'
        message = 'You are running in local mode, please make sure to set up the replica.my.cnf file to run in production mode'
        email_host = 'localhost'
        email_port = 25
        server_email = 'root@localhost'
        admins = []

        databases = {
            'default': {
                'ENGINE': 'django.db.backends.sqlite3',
                'NAME':  BASE_DIR / 'db.sqlite3',
            }
        }

    return {
        'DEBUG': debug,
        'ALLOWED_HOSTS': hosts,
        'SOCIAL_AUTH_MEDIAWIKI_CALLBACK': callback,
        'DATABASES': databases,
        'MESSAGE': message,
        'EMAIL_HOST': email_host,
        'EMAIL_PORT': email_port,
        'SERVER_EMAIL': server_email,
        'ADMINS': admins
    }

settings = configure_settings()
DEBUG = settings['DEBUG']
ALLOWED_HOSTS = settings['ALLOWED_HOSTS']
SOCIAL_AUTH_MEDIAWIKI_CALLBACK = settings['SOCIAL_AUTH_MEDIAWIKI_CALLBACK']
DATABASES = settings['DATABASES']
EMAIL_HOST = settings['EMAIL_HOST']
EMAIL_PORT = settings['EMAIL_PORT']
SERVER_EMAIL = settings['SERVER_EMAIL']
ADMINS = settings['ADMINS']
print(settings['MESSAGE'])