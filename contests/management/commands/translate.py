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
                if language_code == 'en':
                    po = polib.pofile(po_path, encoding='utf-8')
                    self.synchronize_po_with_json(po, translations, filepath)

                # Compile the messages
                mo = polib.MOFile()
                mo.metadata = po.metadata
                for entry in po:
                    mo.append(entry)
                mo.save(po_path.replace('.po', '.mo'))

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

        # Check for missing translations
        for entry in po:
            if entry.msgstr == '':
                entry.msgstr = entry.msgid

        po.save(po_path)
        print(f"Successfully converted to {po_path}")
        return po

    def synchronize_po_with_json(self, po, translations, json_path):
        # Flatten JSON for easy lookup
        flat_json = self.flatten_dict(translations)

        # 1. Add new PO entries to the JSON file
        for entry in po:
            if entry.msgctxt not in flat_json:
                # New message found, add it to the JSON
                if entry.msgctxt is not None:
                    flat_json[entry.msgctxt] = entry.msgid
                    print(f"New message added to JSON: {entry.msgctxt}")
                else:
                    raise ValueError(f"Message without context in PO file: {entry.msgid}")

        # 2. Remove entries from the JSON file if not present in the PO file
        json_keys_to_remove = []
        for key in flat_json:
            if not any(entry.msgctxt == key for entry in po):
                # Message not found in PO, mark it for removal from JSON
                json_keys_to_remove.append(key)

        for key in json_keys_to_remove:
            del flat_json[key]
            print(f"Message removed from JSON: {key}")

        # 3. Check for discrepancies between JSON and PO messages
        for entry in po:
            msgid_sub = re.sub(r'\%\((\d+)\)s', '$\\1', entry.msgid)
            if entry.msgctxt in flat_json and flat_json[entry.msgctxt] != msgid_sub:
                print(f"Warning: Different message in PO and JSON for context '{entry.msgctxt}': PO='{msgid_sub}' JSON='{flat_json[entry.msgctxt]}'")

        # 4. Save the updated JSON file
        updated_json = self.unflatten_dict(flat_json)
        with open(json_path, 'w', encoding='utf-8') as f:
            json.dump(updated_json, f, ensure_ascii=False, indent=4)
        print(f"Successfully synchronized {json_path} with the PO file.")
        return po

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

    def unflatten_dict(self, flat_dict, sep='.'):
        """Unflattens a dictionary."""
        unflattened = {}
        for key, value in flat_dict.items():
            keys = key.split(sep)
            d = unflattened
            for k in keys[:-1]:
                d = d.setdefault(k, {})
            d[keys[-1]] = value
        return unflattened
    

    

    

