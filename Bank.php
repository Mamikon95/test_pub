<?php

namespace backend\models\finance\report;

use backend\models\AccountSubcounts1;
use backend\models\finance\document\BaseDocument;
use backend\models\search\finance\report\CashboxSearch;
use common\helpers\Utils;
use common\models\Account;
use common\models\Document;
use yii\helpers\ArrayHelper;


class Bank extends BaseDocument
{

    protected static $tableComment = 'Банковские документы';

    public const ACCOUNT_ID = Account::VALUE_BANK;

    public function behaviors()
    {
        return ArrayHelper::merge(
            parent::behaviors(),
            [
                # custom behaviors
            ]
        );
    }

    public function rules()
    {
        return ArrayHelper::merge(
            parent::rules(),
            [
                # custom validation rules
            ]
        );
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(
            parent::attributeLabels(),
            [
                'total' => 'Сумма',
                'to_subcount_1_object' => 'Получатель/Плательщик',
            ]
        );
    }

    protected static function _getColumnsByArray(array $gridColumns)
    {
        return parent::_getColumnsByArray(
            [
                '_serial',
                'created:date|d.m.Y',
                'registration_date:date|d.m.Y',
                '_label_update_url',
                'operation_type_id',
                'to_subcount_1_object',
                'total',
                'organization_id',
                'responsible_id',
//                'comment',
                'comment_short',
            ]
        );
    }

    protected static function _getColumnsFullAssoc()
    {
        $contractors = \backend\models\Contractors::getListData();
        $clients = \backend\models\Client::getListData();

        $clients_data = [];
        $contractors_data = [];

        foreach ($clients as $key => $item) {
            $clients_data['client_' . $key] = $item;
        }

        foreach ($contractors as $key => $item) {
            $contractors_data['contractor_' . $key] = $item;
        }


        $contractor_client_value = ArrayHelper::getValue($_GET, 'CashboxSearch.to_subcount_1_object');

        $model = new CashboxSearch();
        $model->to_subcount_1_object = $contractor_client_value;

        return array_merge(parent::_getColumnsFullAssoc(), [
            'to_subcount_1_object' => [
                'format' => 'raw',
                'label' => 'Получатель/Плательщик',
                'value' => function ($model) {
                    /* @var $model Document */
                    $target_account_id = self::ACCOUNT_ID;
                    $isTo = $model->to_account_id == $target_account_id;
                    $isFrom = $model->from_account_id == $target_account_id;
                    $value = null;
                    $account_id = null;
                    if ($isTo) {
                        $account_id = $model->getFromAccountId();
                        $value = $account_id ? ($model->from_subcount_1_object ? AccountSubcounts1::sGetTextValue($account_id,
                            $model->from_subcount_1_object) : null) : null;
                    }
                    if ($isFrom) {
                        $account_id = $model->getToAccountId();
                        $value = $account_id ? ($model->to_subcount_1_object ? AccountSubcounts1::sGetTextValue($account_id,
                            $model->to_subcount_1_object) : null) : null;
                    }
                    if (null === $value) {
                        $res = [];
                        foreach ($model->getDocumentItems()->actual()->all() as $document_item) {
                            $di_to_a_id = $document_item->getToAccountId();
                            $di_from_a_id = $document_item->getFromAccountId();
                            if (!$isFrom && !$isTo) {
                                $isTo = $di_to_a_id == $target_account_id;
                                $isFrom = $di_from_a_id == $target_account_id;
                            }
                            if ($isTo) {
                                $account_id = $account_id ?? $di_from_a_id;
                                $value = $account_id ? ($document_item->from_subcount_1_object ? AccountSubcounts1::sGetTextValue($account_id,
                                    $document_item->from_subcount_1_object) : null) : null;
                            }
                            if ($isFrom) {
                                $account_id = $account_id ?? $di_to_a_id;
                                $value = $account_id ? ($document_item->to_subcount_1_object ? AccountSubcounts1::sGetTextValue($account_id,
                                    $document_item->to_subcount_1_object) : null) : null;
                            }
                            $res[] =
                                $value;
                        }
                        if (!$res) {
                            return null;
                        }
                        return implode($res, '<br/>');
                    }
                    return $value;
                },
                /*'filter' => \kartik\select2\Select2::widget([
                    'model' => $model,
                    'attribute' => 'to_subcount_1_object',
                    'data' => ArrayHelper::merge($clients_data, $contractors_data),
                    'options' => ['placeholder' => 'Выберите Получателя/Плательщика ...'],
                    'pluginOptions' => [
                        'allowClear' => true
                    ],
                ]),*/
            ],
            'total' => [
                'format' => 'raw',
                'attribute' => 'total',
                'filter' => false,
                'label' => 'Сумма',
                'value' => function ($model) {
                    /* @var $model Document */
                    return $model->getTotal();
                }
            ],
            'comment_short' => [
                'format' => 'raw',
                'attribute' => 'comment_short',
                'filter' => false,
                'label' => 'Комментарий',
                'value' => function ($model) {
                    /* @var $model Document */
                    return Utils::sGetLimitedStr($model->comment, 40);
                }
            ]
        ]);
    }
}