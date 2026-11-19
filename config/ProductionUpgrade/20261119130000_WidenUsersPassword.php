<?php
declare(strict_types=1);

use Migrations\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

final class WidenUsersPassword extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('users');
        $column = $table->getColumn('password');
        if ($column === null || $column->getType() !== 'string') {
            throw new RuntimeException('Expected users.password to be a VARCHAR column.');
        }
        if ($column->getLimit() >= 255) {
            return;
        }

        $table->changeColumn('password', $column->setLimit(255))->update();
    }

    public function down(): void
    {
        throw new IrreversibleMigrationException('Narrowing users.password could truncate existing password hashes.');
    }
}
