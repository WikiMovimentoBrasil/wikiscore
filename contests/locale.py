import os
from django.conf import settings
from django.conf.locale import LANG_INFO

def get_available_languages():
    locale_dir = os.path.join(settings.BASE_DIR, 'locale')
    languages = []

    if os.path.exists(locale_dir):
        for folder_name in os.listdir(locale_dir):
            if folder_name == 'qqq':  # Skip the message documentation folder
                continue
            folder_path = os.path.join(locale_dir, folder_name)
            if os.path.isdir(folder_path) and os.path.exists(os.path.join(folder_path, 'LC_MESSAGES')):
                folder_name = folder_name.replace('_', '-').lower()
                languages.append((folder_name, folder_name))  # Append the language code and name
    else:
        languages = [('en', 'English')]
                
    return languages

def add_custom_languages():
    if 'xal' not in LANG_INFO:
        LANG_INFO['xal'] = {
            'name': 'Kalmyk',
            'name_local': 'Хальмг',  # Native name
            'bidi': False,            # Set to True if it's a right-to-left language
            'code': 'xal',
        }