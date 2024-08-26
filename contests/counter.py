from django.shortcuts import render
from django.utils import timezone
from contests.models import Contest, Edit
from django.shortcuts import render, redirect, get_object_or_404

def counter_view(request):
    contest_name_id = request.GET.get('contest')
    if not contest_name_id:
        return redirect('/')
    contest = get_object_or_404(Contest, name_id=contest_name_id)

    if request.POST.get('time_round'):
        request_time = request.POST.get('time_round')
    else:
        request_time = timezone.now()

    time_round = request_time.strftime('%Y-%m-%d %H:%M:%S')

    # Query for calculating points from edits
    query = f"""
    SELECT 
        `user_table`.`user_id` AS `id`, 
        `user_table`.`user`, 
        IFNULL(`points`.`sum`, 0) AS `sum`, 
        IFNULL(`points`.`total edits`, 0) AS `total_edits`, 
        IFNULL(`points`.`bytes points`, 0) AS `bytes_points`, 
        IFNULL(`points`.`total pictures`, 0) AS `total_pictures`, 
        IFNULL(`points`.`pictures points`, 0) AS `pictures_points`, 
        IFNULL(`points`.`total points`, 0) AS `total_points` 
    FROM (
        SELECT 
            DISTINCT `contests_edit`.`user_id`, 
            `contests_participant`.`user` 
        FROM 
            `contests_edit` 
        INNER JOIN `contests_participant` ON `contests_participant`.`local_id` = `contests_edit`.`user_id`
    ) AS `user_table` 
    LEFT JOIN (
        SELECT 
            t1.`user_id`, 
            t1.`sum`, 
            t1.`total edits`, 
            t1.`bytes points`, 
            t2.`total pictures`, 
            t2.`pictures points`, 
            (t1.`bytes points` + t2.`pictures points`) AS `total points` 
        FROM (
            SELECT 
                edits_ruled.`user_id`, 
                SUM(edits_ruled.`bytes`) AS `sum`, 
                SUM(edits_ruled.`valid_edits`) AS `total edits`, 
                FLOOR(SUM(edits_ruled.`bytes`) / edits_ruled.`bytes_per_points`) AS `bytes points` 
            FROM (
                SELECT 
                    `contests_edit`.`article_id`, 
                    `contests_edit`.`user_id`, 
                    CASE 
                        WHEN SUM(`contests_evaluation`.`real_bytes`) > `contests_contest`.`max_bytes_per_article` 
                        THEN `contests_contest`.`max_bytes_per_article` 
                        ELSE SUM(`contests_evaluation`.`real_bytes`) 
                    END AS `bytes`, 
                    COUNT(`contests_evaluation`.`valid_edit`) AS `valid_edits`, 
                    `contests_contest`.`bytes_per_points` AS `bytes_per_points` 
                FROM 
                    `contests_edit` 
                    LEFT JOIN `contests_evaluation` ON `contests_edit`.`last_evaluation_id` = `contests_evaluation`.`id` 
                    LEFT JOIN `contests_contest` ON `contests_edit`.`contest_id` = `contests_contest`.`id` 
                WHERE 
                    `contests_edit`.`contest_id` = '{contest.id}' 
                    AND `contests_evaluation`.`valid_edit` = '1' 
                    AND `contests_edit`.`timestamp` < '{time_round}'
                GROUP BY 
                    `contests_edit`.`user_id`, 
                    `contests_edit`.`article_id`
            ) AS edits_ruled 
            GROUP BY 
                edits_ruled.`user_id`
        ) AS `t1` 
        LEFT JOIN (
            SELECT 
                `distinct`.`user_id`, 
                `distinct`.`article_id`, 
                SUM(`distinct`.`pictures`) AS `total pictures`, 
                CASE 
                    WHEN `distinct`.`pictures_per_points` = 0 
                    THEN 0 
                    ELSE FLOOR(SUM(`distinct`.`pictures`) / `distinct`.`pictures_per_points`) 
                END AS `pictures points` 
            FROM (
                SELECT 
                    `contests_edit`.`user_id`, 
                    `contests_edit`.`article_id`, 
                    `contests_evaluation`.`pictures`, 
                    `contests_edit`.`id`, 
                    `contests_contest`.`pictures_per_points` 
                FROM 
                    `contests_edit` 
                LEFT JOIN `contests_evaluation` ON `contests_edit`.`last_evaluation_id` = `contests_evaluation`.`id` 
                LEFT JOIN `contests_contest` ON `contests_edit`.`contest_id` = `contests_contest`.`id` 
                WHERE 
                    `contests_edit`.`contest_id` = '{contest.id}' 
                    AND `contests_evaluation`.`pictures` IS NOT NULL 
                    AND `contests_edit`.`timestamp` < '{time_round}' 
                GROUP BY 
                    CASE 
                        WHEN `contests_contest`.`pictures_mode` = 0 
                        THEN `contests_edit`.`user_id` 
                    END, 
                    CASE 
                        WHEN `contests_contest`.`pictures_mode` = 0 
                        THEN `contests_edit`.`article_id` 
                    END, 
                    CASE 
                        WHEN `contests_contest`.`pictures_mode` = 0 
                        THEN `contests_evaluation`.`pictures` 
                        ELSE `contests_edit`.`id` END
            ) AS `distinct` 
            GROUP BY 
                `distinct`.`user_id`
        ) AS `t2` ON t1.`user_id` = t2.`user_id`
    ) AS `points` ON `user_table`.`user_id` = `points`.`user_id` 
    ORDER BY 
        `points`.`total points` DESC, 
        `points`.`sum` DESC, 
        `user_table`.`user` ASC;
    """

    counter = Edit.objects.raw(query)
    print(contest.start_time)
    print(request_time)

    return render(request, 'counter.html', {
        'contest': contest,
        'counter': counter,
        'date': request_time.strftime('%Y-%m-%d'),
        'time': request_time.strftime('%H:%M:%S'),
        'time_form': request_time.strftime('%Y-%m-%dT%H:%M:%S'),
        'contest_begun': contest.start_time < request_time,
    })
    
