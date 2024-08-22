import os
import json
import re
from django.core.management.base import BaseCommand
from django.conf import settings
from polib import POFile, POEntry

class Command(BaseCommand):
    help = "Convert JSON translations to PO files."

    def handle(self, *args, **options):
        json_dir = os.path.join(settings.BASE_DIR, 'translations')
        for filename in os.listdir(json_dir):
            if filename.endswith('.json'):
                filepath = os.path.join(json_dir, filename)
                translations = self.load_translations(filepath)
                language = filename.split('.')[0]
                po = self.convert_to_po(translations, language)
                po_path = os.path.join(settings.BASE_DIR, 'locale', language, 'LC_MESSAGES', 'django.po')
                self.save_po_file(po, po_path)

    def load_translations(self, filepath):
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)

    def convert_to_po(self, translations, language):
        po = POFile(encoding='utf-8')
        po.metadata = {
            'Content-Type': 'text/plain; charset=utf-8',
            'Content-Transfer-Encoding': '8bit',
            'Language': language,
        }
        for key, value in translations.items():
            if isinstance(value, str):
                # Count the number of placeholders in the string
                count = len(re.findall(r'\$\d+', value))
                if count > 0:
                    # If key is "an-example" and value is "This: $1 example", the msgid will be ".%(an_example_1)s." and msgstr will be "This: %(an_example_1)s example"
                    # If key is "an-example" and value is "This: $1 example $2 good", the msgstr will be ".%(an_example_1)s.%(an_example_2)s." and msgstr will be "This: %(an_example_1)s example %(an_example_2)s good"
                    value = re.sub(r'\$(\d+)', '%(' + key.replace("-", "_") + '_\\1)s', value)
                    key = '.' + '.'.join([f'%({key.replace("-", "_")}_{i+1})s' for i in range(count)]) + '.'
                po.append(POEntry(msgid=key, msgstr=value))
        return po

    def save_po_file(self, po, po_path):
        os.makedirs(os.path.dirname(po_path), exist_ok=True)
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
    

    

    

