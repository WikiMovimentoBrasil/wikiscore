from contests.models import Contest
from .counter import CounterHandler
from datetime import datetime, timedelta
from django.utils.timezone import now


class GraphHandler:
    COLORS = [
        "#fd7f6f", "#7eb0d5", "#b2e061", "#bd7ebe", 
        "#ffb55a", "#ffee65", "#beb9db", "#fdcce5", "#8bd3c7"
    ]

    def __init__(self, contest):
        self.contest = contest
        self.counter = CounterHandler(contest=self.contest)
        self.start_day = self.contest.start_time.strftime("%Y-%m-%d")

    def execute(self):
        if self.is_contest_not_started():
            return self._response_with_empty_graph()

        end_day = self.get_end_day()
        days, last_day = self.prepare_days_range(end_day)
        all_points = self.collect_points_for_days(days + [last_day])
        best_users = self.get_top_users(all_points, last_day)
        datasets_graph = self.build_datasets_graph(best_users, days, all_points)

        total_days = self.get_total_days()
        all_days = list(range(total_days))

        return self._response_with_graph(datasets_graph, all_days)

    def is_contest_not_started(self):
        return now().date() < self.contest.start_time.date()

    def get_end_day(self):
        if now().date() > self.contest.end_time.date():
            end_day = (self.contest.end_time + timedelta(days=1)).strftime("%Y-%m-%d")
            return end_day
        return now().strftime("%Y-%m-%d")

    def prepare_days_range(self, end_day):
        days = self.generate_date_range(self.start_day, end_day)
        last_day = days.pop()
        return days, last_day

    def collect_points_for_days(self, days):
        """Collects points for all the specified days with a single query per day."""
        all_points = {}
        for day in days:
            time_round = f"{day} 23:59:59"
            points = self.counter.get_points(time_round)
            all_points[day] = {point.user: point.total_points for point in points}
        return all_points

    def get_top_users(self, all_points, last_day):
        """Get top 9 users based on their points on the last day."""
        last_day_points = all_points[last_day]
        sorted_users = sorted(last_day_points.items(), key=lambda x: x[1], reverse=True)[:9]
        return dict(sorted_users)

    def build_datasets_graph(self, best_users, days, all_points):
        datasets_graph = []
        colors = self.COLORS.copy()

        for user, _ in best_users.items():
            user_data = [all_points[day].get(user, 0) for day in days]
            color = colors.pop(0)
            datasets_graph.append(self.build_user_dataset(user, user_data, color))

        return datasets_graph

    def build_user_dataset(self, user, user_data, color):
        return {
            'label': user,
            'data': user_data,
            'fill': 'false',
            'borderColor': color,
            'lineTension': 0.1,
        }

    def get_total_days(self):
        return (self.contest.end_time - self.contest.start_time + timedelta(days=1)).days

    def generate_date_range(self, start_day, end_day):
        start = datetime.strptime(start_day, '%Y-%m-%d')
        end = datetime.strptime(end_day, '%Y-%m-%d') + timedelta(days=1)

        return [(start + timedelta(days=i)).strftime('%Y-%m-%d') for i in range((end - start).days)]

    def _response_with_graph(self, datasets_graph, all_days):
        return {
            'contest': self.contest,
            'datasets_graph': datasets_graph,
            'all_days': all_days,
        }

    def _response_with_empty_graph(self):
        return {
            'contest': self.contest,
            'datasets_graph': False,
            'all_days': [],
        }
