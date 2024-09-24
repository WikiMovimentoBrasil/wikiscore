import os
from django.conf import settings
from django.conf.locale import LANG_INFO

def get_available_languages():
    locale_dir = os.path.join(settings.BASE_DIR, 'translations')
    languages = []

    if os.path.exists(locale_dir):
        for file in os.listdir(locale_dir):
            # Drop the ".json" extension
            file = file.split('.')[0]
            if file == 'qqq':  # Skip the message documentation folder
                continue
            languages.append((file, file))  # Append the language code and name
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