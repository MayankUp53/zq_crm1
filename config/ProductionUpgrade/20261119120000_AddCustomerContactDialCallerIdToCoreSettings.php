<?php
declare(strict_types=1);

use Migrations\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class AddCustomerContactDialCallerIdToCoreSettings extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('core_settings');
        if (!$table->hasColumn('customer_contact_dial_caller_id')) {
            $table->addColumn('customer_contact_dial_caller_id', 'string', [
                'limit' => 50,
                'null' => true,
                'default' => '05033106255',
            ])->update();
        }
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException('Dropping this column would remove the configured caller ID.');
    }
}
