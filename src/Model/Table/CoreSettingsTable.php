<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * CoreSettings Model
 */
class CoreSettingsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('core_settings');
        $this->setDisplayField('host_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                    'updated' => 'always',
                ],
            ],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->scalar('customer_contact_dial_caller_id')
            ->maxLength('customer_contact_dial_caller_id', 50)
            ->allowEmptyString('customer_contact_dial_caller_id');
        $validator->scalar('call_history_campaign_id')
            ->maxLength('call_history_campaign_id', 255)
            ->regex('call_history_campaign_id', '/\A(?:0|[1-9][0-9]*(?:,[1-9][0-9]*)*)\z/', '0 またはカンマ区切りのキャンペーンIDを入力してください。')
            ->notEmptyString('call_history_campaign_id');

        return $validator;
    }

    public function getCallHistoryLimitField(): string
    {
        $schema = $this->getConnection()
            ->getSchemaCollection()
            ->describe($this->getTable());

        if ($schema->hasColumn('limit_value')) {
            return 'limit_value';
        }

        return 'limit';
    }
}
