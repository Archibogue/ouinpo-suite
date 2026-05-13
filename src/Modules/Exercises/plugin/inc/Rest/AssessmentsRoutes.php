<?php

namespace Ouinpo\Exercises\Rest;

use Ouinpo\Suite\Core\Capabilities;

/**

 * Legacy REST routes for exercise-based assessments.

 * The current admin workflow for DS is competency-based.

 * Kept for backward compatibility.

 */



use WP_REST_Request;

use WP_REST_Response;

defined('ABSPATH') || exit;



class AssessmentsRoutes {

    const NS = 'ouinpo/v1';



    public static function register() {

        register_rest_route(self::NS, '/assessments', [

            ['methods'=>'GET','callback'=>[__CLASS__,'list'],'permission_callback'=>function(){return Capabilities::can(Capabilities::MANAGE_ASSESSMENTS);}],

            ['methods'=>'POST','callback'=>[__CLASS__,'create'],'permission_callback'=>function(){return Capabilities::can(Capabilities::MANAGE_ASSESSMENTS);}],

        ]);

        register_rest_route(self::NS, '/assessments/(?P<id>\d+)', [

            ['methods'=>'GET','callback'=>[__CLASS__,'get'],'permission_callback'=>function(){return Capabilities::can(Capabilities::MANAGE_ASSESSMENTS);}],

            ['methods'=>'POST','callback'=>[__CLASS__,'grade'],'permission_callback'=>function(){return Capabilities::can(Capabilities::MANAGE_ASSESSMENTS);}],

        ]);

    }



    private static function is_practical_subject(int $exercise_id): bool {

        if ($exercise_id <= 0) {

            return false;

        }



        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';



        return (int) $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*)

             FROM {$p}exam_meta

             WHERE exercise_id = %d

               AND exam_type = 'practical_subject'",

            $exercise_id

        )) > 0;

    }



    public static function list(): WP_REST_Response {

        global $wpdb; $p = $wpdb->prefix.'ouin_exo_';

        $rows = $wpdb->get_results("SELECT * FROM {$p}assessments ORDER BY due_on DESC, id DESC");

        return new WP_REST_Response($rows, 200);

    }



    public static function create(WP_REST_Request $r): WP_REST_Response {

        global $wpdb; $p = $wpdb->prefix.'ouin_exo_';

        $data = [

            'title'=>sanitize_text_field($r['title']),

            'group_id'=>intval($r['group_id'] ?? 0) ?: null,

            'due_on'=>sanitize_text_field($r['due_on'] ?? null),

            'weight'=>floatval($r['weight'] ?? 1),

            'notes'=>wp_kses_post($r['notes'] ?? ''),

        ];

        $wpdb->insert($p.'assessments', $data);

        $aid = (int)$wpdb->insert_id;

        foreach ((array)$r['exercise_ids'] as $eid) {

            $eid = (int) $eid;



            if ($eid <= 0 || self::is_practical_subject($eid)) {

                continue;

            }



            $wpdb->insert($p.'assessment_items', [

                'assessment_id' => $aid,

                'exercise_id'   => $eid

            ]);

        }

        return new WP_REST_Response(['id'=>$aid], 201);

    }



    public static function grade(WP_REST_Request $r): WP_REST_Response {

        global $wpdb; $p = $wpdb->prefix.'ouin_exo_';

        $grades = (array)$r->get_param('results'); // tableau de résultats : user_id, exercise_id, status, badge_ids optionnel

        foreach ($grades as $g) {

            $uid = intval($g['user_id']);

            $eid = intval($g['exercise_id']);

            if ($eid <= 0 || self::is_practical_subject($eid)) {

                continue;

            }

            $raw = (string) ($g['status'] ?? 'none');



            $map = [

                'none'        => 'none',

                'todo'        => 'none',

                'attempted'   => 'attempted',

                'in_progress' => 'attempted',

                'failed'      => 'attempted',

                'abandoned'   => 'attempted',

                'solved'      => 'solved',

                'succeeded'   => 'solved',

            ];



            $status = $map[$raw] ?? 'none';

            $exists = (int)$wpdb->get_var($wpdb->prepare(

                "SELECT COUNT(*) FROM {$p}user_status WHERE user_id=%d AND exercise_id=%d", $uid, $eid

            ));

            $data = [

                'user_id'=>$uid,'exercise_id'=>$eid,'status'=>$status,

                'declared_at'=>($status==='solved')? current_time('mysql'): null,

                'updated_at'=> current_time('mysql'),

            ];

            if ($exists) { $wpdb->update($p.'user_status', $data, ['user_id'=>$uid,'exercise_id'=>$eid]); }

            else { $wpdb->insert($p.'user_status', $data); }



            foreach ((array)($g['badge_ids'] ?? []) as $bid) {

                $has = (int)$wpdb->get_var($wpdb->prepare(

                    "SELECT COUNT(*) FROM {$p}user_badges WHERE user_id=%d AND badge_id=%d", $uid, $bid

                ));

                if (!$has) {

                    $wpdb->insert($p.'user_badges', [

                        'user_id'=>$uid, 'badge_id'=>intval($bid), 'awarded_at'=>current_time('mysql'), 'source'=>'manual'

                    ]);

                }

            }

        }

        return new WP_REST_Response(['ok'=>true], 200);

    }



    public static function get(WP_REST_Request $r): WP_REST_Response {

        global $wpdb; $p = $wpdb->prefix.'ouin_exo_';

        $aid = (int)$r['id'];

        $a = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}assessments WHERE id=%d", $aid));

        $items = $wpdb->get_results($wpdb->prepare(

            "SELECT ai.exercise_id

             FROM {$p}assessment_items ai

             LEFT JOIN {$p}exam_meta em

               ON em.exercise_id = ai.exercise_id

              AND em.exam_type = 'practical_subject'

             WHERE ai.assessment_id = %d

               AND em.exercise_id IS NULL",

            $aid

        ));

        return new WP_REST_Response(['assessment'=>$a,'items'=>$items], 200);

    }

}
