# Generated by Django 5.1 on 2024-08-30 19:06

from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('contests', '0007_group_manager'),
    ]

    operations = [
        migrations.AlterField(
            model_name='contest',
            name='outreach_name',
            field=models.TextField(null=True),
        ),
    ]
