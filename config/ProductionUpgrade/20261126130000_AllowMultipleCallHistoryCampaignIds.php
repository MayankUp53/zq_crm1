<?php
declare(strict_types=1);

use Migrations\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

final class AllowMultipleCallHistoryCampaignIds extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('core_settings');
        $column = $table->getColumn('call_history_campaign_id');
        $options = ['limit' => 255, 'null' => false, 'default' => '0'];
        if ($column === null) {
            $table->addColumn('call_history_campaign_id', 'string', $options)->update();

            return;
        }

        if ($column->getType() === 'integer') {
            $this->execute('UPDATE core_settings SET call_history_campaign_id = 0 WHERE call_history_campaign_id = 29 OR call_history_campaign_id IS NULL');
        }
        $table->changeColumn('call_history_campaign_id', 'string', $options)->update();
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException('Converting campaign IDs to an integer could remove saved campaign lists.');
    }
}
