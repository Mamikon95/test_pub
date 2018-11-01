<?php

namespace backend\controllers\finance\report;

use backend\components\BaseDocumentController;
use backend\models\ClientFoodCalendar;
use yii\db\Expression;
use yii;

class ConversionController extends BaseDocumentController
{
    public function actionIndex($date_start = '', $date_end = '', $organization_id = 5)
    {

        $responsible_id = yii::$app->request->get('responsible_id');
        $params = [
            'date_start' => $date_start,
            'date_end' => $date_end,
            'organization_id' => $organization_id,
            'responsible_id' => $responsible_id,
        ];

        if ($date_start && $date_end) {
            $today = date('Y-m-d');
            $date_start = $date_start > $today ? $today : $date_start;
            $date_end = $date_end > $today ? $today : $date_end;
            $data = $this->_getData($date_start, $date_end,$organization_id,$responsible_id);
            $data = $this->_grouping($data);
            $params['data'] = $data;
        }

        return $this->render('index', $params);
    }

    /**
     * Получаем список клиентов с данными о продлинности
     * @param string $date_start
     * @param string $date_end
     * @param int $organization_id
     * @param string $responsible_id
     * @return array|\yii\db\ActiveRecord[]
     */
    protected function _getData(string $date_start, string $date_end,int $organization_id, $responsible_id)
    {
        $today = date('Y-m-d');
        return ClientFoodCalendar::find()
            ->innerJoin('client c', 'c.id = client_food_calendar.client_id')
            ->andWhere(['client_food_calendar.market_subprogram_id' => 66])
            ->andWhere(['<=', new Expression('IFNULL(NULLIF(client_food_calendar.real_date,"0000-00-00"),client_food_calendar.date)'), $date_end])
            ->andWhere(['>=', new Expression('IFNULL(NULLIF(client_food_calendar.real_date,"0000-00-00"),client_food_calendar.date)'), $date_start])
            ->andWhere(['=','c.organization_id',$organization_id])
            ->andFilterWhere(['in','c.responsible_id', $responsible_id])
            ->select([
                'c.first_name as first_name',
                'c.last_name as last_name',
                'c.middle_name as middle_name',
                'c.id as client_id',
                'c.responsible_id as responsible_id',
                new Expression(
                    <<<SQL
IF((SELECT COUNT(cfc.id) AS count_pub FROM client_food_calendar AS cfc
WHERE cfc.client_id = c.id AND IFNULL(NULLIF(client_food_calendar.real_date,"0000-00-00"),client_food_calendar.date) < IFNULL(NULLIF(cfc.real_date,"0000-00-00"),cfc.date) AND IFNULL(NULLIF(cfc.real_date,"0000-00-00"),cfc.date) < '$today' AND cfc.publish = 1) > 0,1,0) AS lasted
SQL

                )
            ])
            ->groupBy('client_food_calendar.client_id')
            ->orderBy('c.responsible_id')
            ->asArray()
            ->all();
    }

    protected function _grouping($data) {
        if($data) {
            $data_new = [];

            foreach($data as $item) {
                @$data_new[$item['responsible_id']]['clients'][] = $item;
                @$data_new[$item['responsible_id']]['all']['count']++;
                @$data_new[$item['responsible_id']]['all']['lasted'] += $item['lasted'] ? 1 : 0;
                @$data_new[$item['responsible_id']]['all']['not_lasted'] += $item['lasted'] ? 0 : 1;
                @$data_new['all']['count']++;
                @$data_new['all']['lasted'] += $item['lasted'] ? 1 : 0;
                @$data_new['all']['not_lasted'] += $item['lasted'] ? 0 : 1;
            }
        }

        return $data_new;
    }
}