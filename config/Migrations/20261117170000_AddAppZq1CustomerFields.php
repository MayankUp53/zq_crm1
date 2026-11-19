<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddAppZq1CustomerFields extends AbstractMigration
{
    private array $columns = [
        'base_name' => ['string', 255],
        'book_name' => ['string', 255],
        'tel2_only' => ['string', 50],
        'tel3_only' => ['string', 50],
        'sub_phone1' => ['string', 50],
        'sub_phone2' => ['string', 50],
        'sub_phone3' => ['string', 50],
        'sub_phone4' => ['string', 50],
        'sub_phone5' => ['string', 50],
        'collection_cls' => ['string', 50],
        'direct_cls' => ['string', 50],
        'summary' => ['text', null],
        'factory_desc' => ['text', null],
        'remarks1' => ['text', null],
        'remarks2' => ['text', null],
        'remarks5' => ['text', null],
        'remarks6' => ['text', null],
        'reserve1' => ['text', null],
        'reserve2' => ['text', null],
        'reserve5' => ['text', null],
        'sales_rep_name' => ['string', 255],
        'sales_staff_cd' => ['string', 50],
        'closing_date' => ['string', 50],
    ];

    public function up(): void
    {
        if (!$this->hasTable('customers')) {
            return;
        }

        $table = $this->table('customers');
        $modified = false;
        foreach ($this->columns as $name => [$type, $limit]) {
            if ($table->hasColumn($name)) {
                continue;
            }

            $options = ['null' => true, 'default' => null];
            if ($limit !== null) {
                $options['limit'] = $limit;
            }
            $table->addColumn($name, $type, $options);
            $modified = true;
        }

        if ($modified) {
            $table->update();
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('customers')) {
            return;
        }

        $table = $this->table('customers');
        $modified = false;
        foreach (array_keys($this->columns) as $name) {
            if (!$table->hasColumn($name)) {
                continue;
            }

            $table->removeColumn($name);
            $modified = true;
        }

        if ($modified) {
            $table->update();
        }
    }
}
