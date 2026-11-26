<?php
declare(strict_types=1);

use Migrations\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

final class AddCallHistoryCampaignIdToCoreSettings extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('core_settings');
        if (!$table->hasColumn('call_history_campaign_id')) {
            $table->addColumn('call_history_campaign_id', 'integer', [
                'null' => false,
                'default' => 29,
            ])->update();
        }
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException('Dropping this column would remove the configured call history campaign ID.');
    }
}
