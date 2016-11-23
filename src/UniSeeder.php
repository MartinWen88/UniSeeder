<?php

/**
 * Class UniSeeder
 */
class UniSeeder
{
    /**
     * @param int $repeat
     * @param bool $make
     * @return array
     */
    function run(int $repeat = 1, bool $make = true)
    {
        //TODO:: make repeat working for multiple values
        $repeat = 1;
        $fields = $types = $allModels = [];
        $name = 'Tables_in_' . getenv('DB_DATABASE');
        $allResults = $this->getColumnTableCollection();
        $this->checkEnvironmentFile($repeat);

        foreach ($allResults[1] as $tables) {
            $results = $this->getColumnTableCollection($tables->$name);

            foreach ($results[0] as $collection) {
                $types[] = $collection['type'];
                $fields[] = $collection['field'];
            }
            unset($types);
            unset($fields);
            $modelId = $this->trimTableNames($tables->$name);

            if ($this->modelIdExist($modelId, $name)) {
                array_push($allModels, $modelId);
            }
        }
        $ids = $this->setIds($allModels);
        $tableArray = $this->prepareDatabaseIds($ids);
        $readyData = $this->checkSeedValues($tableArray);

        if ($make) {
            $this->fillDataTables($readyData);
            return $readyData;
        }
        return $this->cleanDatabase($readyData);
    }


    /**
     * @param string|null $rawTable
     * @return array
     */
    private function getColumnTableCollection(string $rawTable = null) : array
    {
        $collections = $columnValues = [];
        $tables = DB::select('SHOW TABLES');

        if (count($tables) === 0) {
            \Illuminate\Support\Facades\Artisan::call('migrate');
            $tables = DB::select('SHOW TABLES');
        }

        foreach ($tables as $table) {
            $encodedTables = json_encode($table);

            foreach (json_decode($encodedTables, true) as $decodedTable) {
                $columnValues = $collections = [];
                $columns = $this->getColumnInfo($decodedTable);

                $decodedTable == $rawTable ?
                    $doubleArray = $this->loopColumns($columns, $collections, $columnValues, true) :
                    $doubleArray = $this->loopColumns($columns, $collections, $columnValues);

                if ($decodedTable == $rawTable) {

                    return [$doubleArray[0], $rawTable];
                }

                $collections = $doubleArray[0];
                $columnValues = $doubleArray[1];
                array_push($collections, $columnValues);
            }
        }
        return [$collections, $tables];
    }


    /**
     * @param array $columns
     * @param $collections
     * @param $columnValues
     * @param bool $single
     * @return array
     */
    private function loopColumns(array $columns, $collections, $columnValues, bool $single = false) : array
    {
        foreach ($columns as $column) {
            $columns = json_encode($column);

            foreach (json_decode($columns, true) as $key => $columnValue) {
                $key = strtolower($key);
                switch ($key) {
                    case 'field':
                    case 'type':
                    case 'null':
                    case 'key':
                    case 'default':
                    case 'extra':
                        $columnValues[$key] = $columnValue;
                }
            }
            if (!$single) {
                return [$collections, $columnValues];
            }
            array_push($collections, $columnValues);
        }
        return [$collections, $columnValues];
    }


    /**
     * @param string $table
     * @return string
     */
    private function trimTableNames(string $table) : string
    {
        $trimmedTable = '';
        if ($this->endsWith($table, 's')) {
            $trimmedTable = rtrim($table, 's');

            if ($this->endsWith($trimmedTable, 'ie')) {
                $trimmedTable = rtrim($table, 'ies');
                $trimmedTable .= 'y';
            }

            $trimmedTable .= '_id';
        }
        return $trimmedTable;
    }


    /**
     * @param string $id
     * @param $prefix
     * @return bool
     */
    private function modelIdExist(string $id, $prefix) : bool
    {
        getenv('TEST_VAR_ROLE_TABLE') ?
            $roleTable = getenv('TEST_VAR_ROLE_TABLE') :
            $roleTable = 'roles';

        getenv('TEST_VAR_ROLE_COLUMN') ?
            $roleId = getenv('TEST_VAR_ROLE_COLUMN') :
            $roleId = 'role_id';

        $tables = DB::select('SHOW TABLES');
        $rows = [];

        foreach ($tables as $table) {
            if ($table->$prefix !== $roleTable) {
                $columns = $this->getColumnInfo($table);

                foreach ($columns as $column) {
                    if (array_key_exists('Field', $column) &&
                        $column['Field'] !== $roleId
                    ) {
                        array_push($rows, $column['Field']);
                    }
                }
            }
        }
        return in_array($id, $rows);
    }


    /**
     * @param $table
     * @return array
     */
    private function getColumnInfo($table) : array
    {
        $columns = [];
        $name = 'Tables_in_' . getenv('DB_DATABASE');

        $encodedTable = json_encode($table);
        $decodedTable = json_decode($encodedTable, true);

        if (is_array($decodedTable) && array_key_exists($name, $decodedTable)) {
            $columnArray = DB::select("SHOW COLUMNS FROM " . $decodedTable[$name]);
        } else {
            $columnArray = DB::select("SHOW COLUMNS FROM " . $table);
        }

        foreach ($columnArray as $column) {
            $encodedColumn = json_encode($column);
            $decodedColumn = json_decode($encodedColumn, true);
            array_push($columns, $decodedColumn);
        }
        return $columns;
    }


    /**
     * @param array $modelIdArray
     * @return array
     */
    private function setIds(array $modelIdArray) : array
    {
        getenv('TEST_VAR_ID') ?
            $uniqueID = intval(getenv('TEST_VAR_ID')) :
            $uniqueID = 7000;

        $modelAmount = 0;
        $tableIds = $tableColumn = [];

        do {
            $ids[] = $uniqueID;
            $modelsArray[$modelAmount] = $modelIdArray[$modelAmount];
            $uniqueID++;
            $modelAmount++;
        } while ($modelAmount != count($modelIdArray));

        $modelIds = array_combine($modelsArray, $ids);
        foreach ($modelIds as $modelsArray => $id) {
            $tableIds[$id] = $this->findTableByModel($modelsArray);
        }

        foreach ($tableIds as $id => $table) {
            foreach ($modelIds as $key => $modelId) {
                if ($modelId == $id) {
                    $tableColumn[$table][$key] = $modelId;
                    $tableColumn[$table]['id'] = $modelId;
                }
            }
        }
        return $tableColumn;
    }


    /**
     * @param string $model
     * @return string
     */
    private function findTableByModel(string $model) : string
    {
        $trimmedTable = '';
        if ($this->endsWith($model, '_id')) {
            $trimmedTable = rtrim($model, '_id');

            if ($this->endsWith($trimmedTable, 'y')) {
                $trimmedTable = rtrim($trimmedTable, 'y');
                $trimmedTable .= 'ie';
            }
            $trimmedTable .= 's';
        }
        return $trimmedTable;
    }


    /**
     * @param array $tableModelIds
     * @return array
     */
    private function prepareDatabaseIds(array $tableModelIds) : array
    {
        $cleanTableArray = [];
        foreach ($tableModelIds as $table => $modelIds) {
            foreach (\Schema::getColumnListing($table) as $column) {
                if ($column === 'id') {
                    $cleanTableArray[$table][$column] = $modelIds['id'];
                    unset($tableModelIds[$table]['id']);
                } else {
                    $cleanTableArray[$table][$column] = 'TEST_' . $column;
                }
            }
        }
        foreach (array_divide($tableModelIds)[1] as $modelId) {
            foreach ($cleanTableArray as $table => $cleanArray) {
                foreach ($cleanArray as $column => $value) {
                    if (array_divide($modelId)[0][0] == $column) {
                        $cleanTableArray[$table][$column] = array_divide($modelId)[1][0];
                    }
                }
            }
        }
        return $cleanTableArray;
    }


    /**
     * @param array $tableArray
     * @return array
     */
    private function checkSeedValues(array $tableArray) : array
    {
        foreach ($tableArray as $table => $columns) {
            $rawColumns = $this->getColumnInfo($table);
            foreach ($rawColumns as $rawColumn) {
                switch ($rawColumn['Type']) {
                    case 'int(10) unsigned':
                        $tableArray[$table] = $this->updateColumnToInt($tableArray[$table], $table, $rawColumn);
                        break;
                    case 'int(11)':
                        $tableArray[$table] = $this->updateColumnToInt($tableArray[$table], $table, $rawColumn);
                        break;
                    case 'varchar(255)':
                        $tableArray[$table] = $this->updateValuesMorePersonal($tableArray[$table], $table, $rawColumn);
                        break;
                    case 'varchar(100)':

                        break;
                    case 'timestamp':
                        $tableArray[$table] = $this->updateColumnToTimestamp($tableArray[$table], $table, $rawColumn);
                        break;
                    case 'date':
                        $tableArray[$table] = $this->updateColumnToTimestamp($tableArray[$table], $table, $rawColumn);
                        break;
                    case 'text':

                        break;
                    case $this->startsWith($rawColumn['Type'], 'enum') ? true : false:
                        $tableArray[$table] = $this->updateColumnToEnumValue($tableArray[$table], $table, $rawColumn);
                        break;
                    case $this->startsWith($rawColumn['Type'], 'decimal') ? true : false:
                        $tableArray[$table] = $this->updateColumnToDecimalValue($tableArray[$table], $table, $rawColumn);
                        break;
                    case $this->startsWith($rawColumn['Type'], 'tinyint') ? true : false:
                        $tableArray[$table] = $this->updateColumnToTinyIntOrBool($tableArray[$table], $table, $rawColumn);
                        break;
                    default:
                        \Log::info($rawColumn['Type']);
                        break;
                }
            }
        }
        return ($tableArray);
    }


    /**
     * @param array $tableArrayToBeUpdated
     * @param $table
     * @param $rawColumn
     * @return array
     */
    private function updateColumnToInt(array $tableArrayToBeUpdated, $table, $rawColumn) : array
    {
        getenv('TEST_VAR_ROLE_COLUMN') ? $roles = getenv('TEST_VAR_ROLE_COLUMN') : $roles = 'role_id';

        if ($tableArrayToBeUpdated[$rawColumn['Field']] === 'TEST_' . $roles) {
            if (DB::table($table)->first() != null) {
                $tableArrayToBeUpdated[$rawColumn['Field']] = DB::table($table)->first()->$roles;
            } else {
                $tableArrayToBeUpdated[$rawColumn['Field']] = 1;
            }
        }
        if (!is_int($tableArrayToBeUpdated[$rawColumn['Field']])) {
            $tableArrayToBeUpdated[$rawColumn['Field']] = random_int(200, 300);
        }
        return $tableArrayToBeUpdated;
    }


    /**
     * @param array $tableArrayToBeUpdated
     * @param $table
     * @param $rawColumn
     * @return array
     */
    private function updateColumnToTimestamp(array $tableArrayToBeUpdated, $table, $rawColumn) : array
    {
        if ($tableArrayToBeUpdated[$rawColumn['Field']] === 'TEST_deleted_at') {
            $tableArrayToBeUpdated[$rawColumn['Field']] = null;
        } else {
            $tableArrayToBeUpdated[$rawColumn['Field']] = \Carbon\Carbon::now()->toDateTimeString();
        }
        return $tableArrayToBeUpdated;
    }

    /**
     * @param array $tableArrayToBeUpdated
     * @param $table
     * @param $rawColumn
     * @return array
     */
    private function updateColumnToEnumValue(array $tableArrayToBeUpdated, $table, $rawColumn) : array
    {
        $enum = str_replace("enum(", '', $rawColumn['Type']);
        $enum = str_replace(")", '', $enum);
        $enum = str_replace("'", '', $enum);
        $enumValues = explode(',', $enum);
        $tableArrayToBeUpdated[$rawColumn['Field']] = $enumValues[0];
        return $tableArrayToBeUpdated;
    }


    /**
     * @param array $tableArrayToBeUpdated
     * @param $table
     * @param $rawColumn
     * @return array
     */
    private function updateColumnToDecimalValue(array $tableArrayToBeUpdated, $table, $rawColumn) : array
    {
        $decimal = str_replace('decimal(', '', $rawColumn['Type']);
        $decimal = str_replace(')', '', $decimal);
        $decimal = str_replace(',', '.', $decimal);
        if ($decimal != null) {
            $tableArrayToBeUpdated[$rawColumn['Field']] = $decimal;
        } else {
            $tableArrayToBeUpdated[$rawColumn['Field']] = 0.00;
        }
        return $tableArrayToBeUpdated;
    }


    /**
     * @param array $tableArrayToBeUpdated
     * @param $table
     * @param $rawColumn
     * @return array
     */
    private function updateValuesMorePersonal(array $tableArrayToBeUpdated, $table, $rawColumn) : array
    {
        $emailNames = ['user_email', 'user_mail', 'users_email', 'users_mail', 'mail', 'email', 'e-mail', 'user_e-mail'];
        $passwords = ['user_password', 'pass', 'password', 'users_password', 'user_pass', 'users_pass'];
        $userTables = ['user', 'users', 'usercredential', 'usercredentials', 'user_credential', 'users_credentials'];
        $userNames = ['name', 'first_name', 'firstname'];
        getenv('TEST_VAR_MAIL') ? $fakeMail = getenv('TEST_VAR_MAIL') : $fakeMail = 'fake@email.com';
        getenv('TEST_VAR_CRYPT') ? $crypt = getenv('TEST_VAR_CRYPT') : $crypt = 'bcrypt';
        getenv('TEST_VAR_PASS') ? $pass = getenv('TEST_VAR_PASS') : $pass = 'secret';
        getenv('TEST_VAR_NAME') ? $name = getenv('TEST_VAR_NAME') : $name = 'fakeName';

        if (in_array($rawColumn['Field'], $emailNames)) {
            $tableArrayToBeUpdated[$rawColumn['Field']] = $fakeMail;
        }
        if (in_array($rawColumn['Field'], $passwords)) {

            $tableArrayToBeUpdated[$rawColumn['Field']] = $crypt($pass);
        }
        if (in_array($table, $userTables) && in_array($rawColumn['Field'], $userNames)) {
            $tableArrayToBeUpdated[$rawColumn['Field']] = $name;
        }
        return $tableArrayToBeUpdated;
    }


    /**
     * @param array $tableArrayToBeUpdated
     * @param $table
     * @param $rawColumn
     * @return array
     */
    private function updateColumnToTinyIntOrBool(array $tableArrayToBeUpdated, $table, $rawColumn) : array
    {
        if ($rawColumn['Default'] !== null && $rawColumn['Default'] !== '') {
            $tableArrayToBeUpdated[$rawColumn['Field']] = $rawColumn['Default'];
        } else {
            $tableArrayToBeUpdated[$rawColumn['Field']] = 0;
        }
        return $tableArrayToBeUpdated;
    }


    /**
     * @param $data
     * @return bool
     */
    private function fillDataTables($data)
    {
        foreach ($data as $key => $values) {
            $tableCurrent = DB::table($key)->get();
            $decodedCurrents = json_decode(json_encode($tableCurrent), true);
            foreach ($decodedCurrents as $currents) {
                foreach ($currents as $curKey => $current) {
                    foreach ($values as $valKey => $value) {
                        if ($current === $value) {
                            return false;
                        }
                    }
                }
            }
            DB::table($key)->insert($values);
        }
    }


    /**
     * @param $runs
     * @return bool
     */
    private function checkEnvironmentFile($runs)
    {
        if (!is_file($_SERVER['PWD'] . DIRECTORY_SEPARATOR . '.env')) {
            dd('no .env file found');
        }
        $environmentValues = file($_SERVER['PWD'] . DIRECTORY_SEPARATOR . '.env');
        $testPass = 'TEST_VAR_PASS';
        $testRoleTab = 'TEST_VAR_ROLE_TABLE';
        $testRoleCol = 'TEST_VAR_ROLE_COLUMN';
        $testCrypt = 'TEST_VAR_CRYPT';
        $testID = 'TEST_VAR_ID';
        $testName = 'TEST_VAR_NAME';
        $testMail = 'TEST_VAR_MAIL';
        $values = [$testPass, $testRoleTab, $testRoleCol, $testCrypt, $testID, $testName, $testMail];
        $multiValues = [];

        $a = $b = $c = 1;
        if ($runs > 1) {
            for ($i = 1; $i < $runs; $i++) {
                foreach ($values as $key => $value) {
                    if ($key == 4 || $key == 5 || $key == 6) {
                        $multiValues[] = $value . $i;
                    }
                }
            }
            $values = (array_merge($values, $multiValues));
        }

        foreach ($environmentValues as $envKey => $environmentValue) {
            foreach ($values as $key => $value) {
                if ($this->startsWith($environmentValue, $value)) {
                    unset($values[$key]);
                }
            }
        }

        if (count($values) === 7 || count($values) === 7 + (($runs - 1) * 3)) {
            file_put_contents($_SERVER['PWD'] . DIRECTORY_SEPARATOR . '.env', "\n\n", FILE_APPEND);
        }

        if (!empty($values)) {
            foreach ($values as $value) {
                switch ($value) {
                    case $this->startsWith($value, 'TEST_VAR_ID'):
                        $value .= '=' . $a . '8000' . "\n";
                        $a++;
                        break;

                    case $this->startsWith($value, 'TEST_VAR_NAME'):
                        $value .= '=FakeName' . $b . "\n";
                        $b++;
                        break;

                    case $this->startsWith($value, 'TEST_VAR_MAIL'):
                        $value .= '=fake' . $c . '@mail.com' . "\n";
                        $c++;
                        break;

                    case 'TEST_VAR_PASS':
                        $value .= '=secret' . "\n";
                        break;

                    case 'TEST_VAR_ROLE_TABLE':
                        $value .= '=roles' . "\n";
                        break;

                    case 'TEST_VAR_ROLE_COLUMN':
                        $value .= '=role_id' . "\n";
                        break;

                    case 'TEST_VAR_CRYPT':
                        $value .= '=bcrypt' . "\n";
                        break;
                }
                file_put_contents($_SERVER['PWD'] . DIRECTORY_SEPARATOR . '.env', $value, FILE_APPEND);
            }
        }
    }


    /**
     * @param $data
     * @return bool
     */
    private function cleanDatabase($data)
    {
        foreach ($data as $table => $values) {
            foreach ($values as $field => $value) {
                DB::STATEMENT('SET FOREIGN_KEY_CHECKS=0');
                DB::table($table)->where($field, $value)->delete();
                DB::STATEMENT('SET FOREIGN_KEY_CHECKS=1');
            }
            unset($data[$table]);
        }
        return $data;
    }


    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    function endsWith(string $haystack, string $needle) : bool
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }


    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    private function startsWith(string $haystack, string $needle) : bool
    {
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }
}