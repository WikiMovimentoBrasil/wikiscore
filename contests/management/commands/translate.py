import os
import json
import re
import polib
from django.core.management.base import BaseCommand
from django.core.management import call_command
from django.conf import settings

class Command(BaseCommand):
    help = "Convert JSON translations to PO files."

    def handle(self, *args, **options):
        json_dir = os.path.join(settings.BASE_DIR, 'translations')
        for filename in os.listdir(json_dir):
            if filename.endswith('.json'):
                filepath = os.path.join(json_dir, filename)
                translations = self.load_translations(filepath)
                language_code = filename.split('.')[0]
                language_code = self.convert_language_code(language_code)
                
                call_command('makemessages', f'-l{language_code}')
                po_path = os.path.join(settings.BASE_DIR, 'locale', language_code, 'LC_MESSAGES', 'django.po')
                po = self.convert_to_po(translations, language_code, po_path)

    def load_translations(self, filepath):
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)

    def convert_language_code(self, code):
        parts = code.split('-')
        language = parts[0]
        if len(parts) == 1:
            return language
        region = parts[1]
        if len(region) == 2:
            return f'{language}_{region.upper()}'
        else:
            return f'{language}_{region[0].upper()}{region[1:]}'

    def convert_to_po(self, translations, language, po_path):
        # Load the .po file
        po = polib.pofile(po_path, encoding='utf-8')
        
        for key, value in self.flatten_dict(translations).items():
            if isinstance(value, str):
                # Count the number of placeholders in the string
                count = len(re.findall(r'\$\d+', value))
                if count > 0:
                    # Change placeholders from $1 to %(1)s
                    value = re.sub(r'\$(\d+)', '%(\\1)s', value)
                entry = po.find(key, by='msgctxt')
                if entry:
                    entry.msgstr = value

        po.save(po_path)
        print(f"Successfully converted to {po_path}")

    def flatten_dict(self, d, parent_key='', sep='.'):
        """Flattens a nested dictionary."""
        items = []
        for k, v in d.items():
            new_key = f'{parent_key}{sep}{k}' if parent_key else k
            if isinstance(v, dict):
                items.extend(self.flatten_dict(v, new_key, sep=sep).items())
            else:
                items.append((new_key, v))
        return dict(items)
    

    

    

