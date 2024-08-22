from django.db import models

class Group(models.Model):
    name = models.CharField(max_length=100, unique=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    def __str__(self):
        return self.name

class Contest(models.Model):
    name_id = models.CharField(max_length=30, unique=True)
    start_time = models.DateTimeField()
    end_time = models.DateTimeField()
    name = models.TextField()
    group = models.ForeignKey('Group', on_delete=models.SET_NULL, null=True)
    revert_time = models.SmallIntegerField(default=24)
    official_list_pageid = models.IntegerField()
    category_pageid = models.IntegerField(blank=True, null=True)
    category_petscan = models.IntegerField(blank=True, null=True)
    endpoint = models.URLField()
    api_endpoint = models.URLField()
    outreach_name = models.TextField()
    campaign_event_id = models.IntegerField(default=None, blank=True, null=True)
    bytes_per_points = models.IntegerField(default=3000)
    max_bytes_per_article = models.IntegerField(default=90000)
    minimum_bytes = models.IntegerField(blank=True, null=True)
    pictures_per_points = models.SmallIntegerField(default=5)
    pictures_mode = models.SmallIntegerField(default=0)
    max_pic_per_article = models.SmallIntegerField(blank=True, null=True)
    theme = models.TextField()
    color = models.TextField(blank=True, default='')
    started_update = models.DateTimeField(blank=True, null=True)
    finished_update = models.DateTimeField(blank=True, null=True)
    next_update = models.DateTimeField(blank=True, null=True)

    def __str__(self):
        return self.name_id

class Evaluator(models.Model):
    user = models.ForeignKey('credentials.CustomUser', on_delete=models.PROTECT)
    contest = models.ForeignKey('Contest', on_delete=models.CASCADE)
    user_status = models.CharField(max_length=1, default='P')

    def __str__(self):
        return self.user.username

    class Meta:
        unique_together = ['user', 'contest']

class Article(models.Model):
    contest = models.ForeignKey('Contest', on_delete=models.CASCADE)
    articleID = models.IntegerField()
    title = models.TextField()
    active = models.BooleanField(default=True)

    def __str__(self):
        return (f"{self.contest.name_id} - {self.articleID} - {self.title}")


class Participant(models.Model):
    contest = models.ForeignKey('Contest', on_delete=models.CASCADE)
    user = models.CharField(max_length=100)
    timestamp = models.DateTimeField(blank=True)
    global_id = models.IntegerField()
    local_id = models.IntegerField(blank=True)
    attached = models.DateTimeField(blank=True)

    def __str__(self):
        return self.user

    class Meta:
        unique_together = ['contest', 'user']

class ParticipantEnrollment(models.Model):
    contest = models.ForeignKey('Contest', on_delete=models.CASCADE)
    user = models.ForeignKey('Participant', on_delete=models.CASCADE)
    enrolled = models.BooleanField(default=True)
    when = models.DateTimeField(auto_now_add=True)

    def __str__(self):
        return (f"{self.contest.name_id} - {self.user.user}")


class Edit(models.Model):
    contest = models.ForeignKey('Contest', on_delete=models.CASCADE)
    diff = models.IntegerField()
    article = models.ForeignKey('Article', on_delete=models.SET_NULL, null=True)
    timestamp = models.DateTimeField(blank=True)
    user_id = models.IntegerField()
    participant = models.ForeignKey('Participant', on_delete=models.SET_NULL, null=True)
    orig_bytes = models.IntegerField(blank=True, default=0)
    new_page = models.BooleanField(default=False)

    def __str__(self):
        return (f"{self.contest.name_id} - {self.diff}")

    class Meta:
        unique_together = ['contest', 'diff']


class Qualification(models.Model):
    STATUS_CHOICE = (
        ('0', 'Reverted'),
        ('1', 'Active'),
    )
    contest = models.ForeignKey('Contest', on_delete=models.CASCADE)
    diff = models.ForeignKey('Edit', on_delete=models.CASCADE)
    evaluator = models.ForeignKey('Evaluator', on_delete=models.SET_NULL, null=True)
    status = models.CharField(max_length=1, choices=STATUS_CHOICE, default='1')
    when = models.DateTimeField(auto_now_add=True)

    def __str__(self):
        return (f"{self.contest.name_id} - {self.diff.diff}")


class Evaluation(models.Model):
    STATUS_CHOICE = (
        ('0', 'Pending'),
        ('1', 'Done'),
        ('2', 'Hold'),
        ('3', 'Skipped'),
    )
    contest = models.ForeignKey('Contest', on_delete=models.CASCADE)
    evaluator = models.ForeignKey('Evaluator', on_delete=models.SET_NULL, null=True)
    edit = models.ForeignKey('Edit', on_delete=models.CASCADE)
    valid_edit = models.BooleanField(default=False)
    pictures = models.SmallIntegerField(default=0)
    real_bytes = models.IntegerField(blank=True, default=0)
    status = models.CharField(max_length=1, choices=STATUS_CHOICE, default='0')
    when = models.DateTimeField(auto_now_add=True)
    obs = models.TextField(blank=True, null=True)

    def __str__(self):
        return (f"{self.contest.name_id} - {self.edit.diff} - {self.evaluator.user.username} - {self.status} - {self.when}")