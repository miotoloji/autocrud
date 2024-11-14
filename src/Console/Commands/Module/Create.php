<?php

namespace Miotoloji\AutoCrud\Console\Commands\Module;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class Create extends Command
{

    protected $signature = 'module:create {name} {--table=} {--created=} {--updated=}';
    protected $description = 'Command description';
    protected $files = [
        'Controllers' => [],
        'Helpers' => [],
        'Models' => [],
        'Providers' => [],
        'Requests' => [],
        'Resources' => [],
        'routes' => []
    ];

    public function handle()
    {
        $tableName = $this->option('table') ?? $this->argument('name');
        $sqlFile = __DIR__ . '../../../../Sql/' . Config::get('database.default') . '/getTableData.sql';
        if(is_dir(base_path('app/'.$this->argument('name')))){
            $this->comment('Module already exists!');exit;
        }
        if (!file_exists($sqlFile)) {
            $this->comment('Connection type not supported, Supported Connections (pgsql, mysql, mariadb)');
            exit;
        }
        $qry = str_replace('{%TABLENAME%}', $tableName, file_get_contents($sqlFile));
        $checkTable = DB::select($qry);
        if (!$checkTable) {
            $this->comment('Table data cannot read.');
            exit;
        }
        $sql2 = str_replace('{%TABLENAME%}', $tableName, file_get_contents(__DIR__ . '../../../../Sql/' . Config::get('database.default') . '/getTableKeys.sql'));
        $keys = [];
        $tableKeys = DB::select($sql2);
        foreach ($tableKeys as $key => $value) {
            if (!empty($value->constraint_type) && $value->constraint_type == 'PRIMARY KEY') {
                $keys['PrimaryKey'][] = $value->column_name;
            } else {
                $keys[$value->constraint_name][] = $value;
            }
        }

        $this->generateModel($this->argument('name'), ['name' => $tableName, 'columns' => $checkTable, 'keys' => $keys],);
        $this->generateRequest($this->argument('name'), ['name' => $tableName, 'columns' => $checkTable, 'keys' => $keys]);
        $this->generateController($this->argument('name'));
        $this->generateResource($this->argument('name'));
        $this->generateProvider($this->argument('name'));
        $this->generateHelper($this->argument('name'));
        $this->generateRoute($this->argument('name'));
        if(mkdir(base_path('app/'.$this->argument('name')))){
            foreach($this->files as $folder => $files){
                if(mkdir(base_path('app/'.$this->argument('name').'/'.$folder))){
                    foreach($files as $fileName => $file){
                        $fileN = fopen(base_path('app/'.$this->argument('name').'/'.$folder.'/'.$fileName.'.php'), 'w+');
                        fwrite($fileN, $file);
                        fclose($fileN);
                    }
                }
            }
        }
    }

    protected function generateController($name)
    {
        $this->files['Controllers'][$name . 'Controller'] = $this->generateStandartFile([['{%MODULENAME%}'], [$name]], __DIR__ . '../../../../Templates/Controllers/CrudApiController.template');
    }

    protected function generateModel($name, $tableData)
    {
        $fillables = 'protected $fillable = [';
        $hiddens = 'protected $hidden = [';
        $casts = 'protected $casts = [';
        foreach ($tableData['columns'] as $c) {
            $fillables .= '\'' . $c->column_name . '\',';
            $casts .= '\'' . $c->column_name . '\' => \'' . $this->resolveColumnType($c) . '\',';
            if (preg_match('/(?i)password/', $c->column_name) || preg_match('/(?i)pin/', $c->column_name) || preg_match('/(?i)token/', $c->column_name))
                $hiddens .= '\'' . $c->column_name . '\',';

        }
        $fillables = rtrim($fillables, ',');
        $casts = rtrim($casts, ',');
        $hiddens = rtrim($hiddens, ',');
        $fillables .= '];';
        $casts .= '];';
        $hiddens .= '];';

        $replaces = [
            [
                '{%MODULENAME%}',
                '{%TABLENAME%}',
                '{%PRIMARYKEY%}',
                '{%CREATED%}',
                '{%UPDATED%}',
                '{%FILLABLE_FIELDS%}',
                '{%HIDDEN_FIELDS%}',
                '{%CASTS%}'
            ],
            [
                $name,
                $tableData['name'],
                $tableData['keys']['PrimaryKey'][0],
                $this->option('created') ?? 'created_at',
                $this->option('updated') ?? 'updated_at',
                $fillables,
                $hiddens,
                $casts

            ]
        ];
        $this->files['Models'][$name] = $this->generateStandartFile($replaces, __DIR__ . '../../../../Templates/Models/CrudModel.template');
    }

    protected function generateRequest($name, $tableData)
    {
        $rules = [];
        foreach ($tableData['columns'] as $c) {
            if ($c->column_name == $tableData['keys']['PrimaryKey'][0])
                continue;

            if ($c->is_nullable == 'NO')
                $rules[$c->column_name][] = 'required';
            if ($c->is_nullable == 'YES')
                $rules[$c->column_name][] = 'present';
            if ($c->character_maximum_length)
                $rules[$c->column_name][] = 'max:' . $c->character_maximum_length;
            if ($c->typs)
                $rules[$c->column_name][] = 'in:' . $c->typs;
            if ($c->udt_name == 'bool')
                $rules[$c->column_name][] = 'boolean';
            if (in_array($c->udt_name, ['date', 'datetime', 'timestamp']))
                $rules[$c->column_name][] = 'date';
            if (in_array($c->udt_name, ['int2', 'int4', 'int8', 'integer', 'int', 'float', 'float4', 'float8', 'double', 'decimal', 'numeric']))
                $rules[$c->column_name][] = 'numeric';
            if ($c->column_name == 'email' || $c->column_name == 'Email' || $c->column_name == 'EMAIL')
                $rules[$c->column_name][] = 'email';
            if (preg_match('/(?i)password/', $c->column_name))
                $rules[$c->column_name][] = 'confirmed';
            if ($c->udt_name == 'json')
                $rules[$c->column_name][] = 'json';
        }
        if (!empty($tableData['keys'])) {
            foreach ($tableData['keys'] as $kk => $kv) {
                $tmpRule = '';
                if ($kk == 'PrimaryKey')
                    continue;
                foreach ($kv as $kvk => $kvv) {
                    //unique:Users,Email,NULL,UserId,MerchantId,' . $this->request->get('MerchantId')
                    if (!empty($kvv->constraint_type) && $kvv->constraint_type == 'FOREIGN KEY') {
                        if ($kvv->ordinal_position == 1) {
                            $tmpRule = 'exists:' . $kvv->foreign_table_name . ',' . $kvv->column_name;
                        }
                    } else if (!empty($kvv->constraint_type) && $kvv->constraint_type == 'UNIQUE') {
                        if ($kvv->ordinal_position == 1) {
                            $tmpRule = 'unique:' . $kvv->table_name . ',' . $kvv->column_name . ',NULL,' . $tableData['keys']['PrimaryKey'][0];
                        } else {
                            $tmpRule = 'unique:' . $kvv->table_name . ',' . $kvv->foreign_column_name . ',NULL,' . $tableData['keys']['PrimaryKey'][0] . ',' . $kvv->column_name . ',{$this->request->get(\'' . $kvv->column_name . '\')}';
                        }
                    }
                    $rules[$kv[0]->column_name][$kk] = $tmpRule;
                }
            }
        }
        $ruleTxt = '';
        foreach ($rules as $rk => $rv) {
            $ruleTxt .= '"' . $rk . '" => "';
            foreach ($rv as $rvk) {
                $ruleTxt .= $rvk . '|';
            }
            $ruleTxt = rtrim($ruleTxt, '|');
            $ruleTxt .= '",' . PHP_EOL;
        }
        $replaces = [
            [
                '{%MODULENAME%}',
                '{%RULES%}'
            ],
            [
                $name,
                $ruleTxt,
            ]
        ];


        $this->files['Requests'][$name . 'Request'] = $this->generateStandartFile($replaces, __DIR__ . '../../../../Templates/Requests/CrudApiRequest.template');
        $this->files['Requests'][$name . 'FilterRequest'] = $this->generateStandartFile([['{%MODULENAME%}'], [$name]], __DIR__ . '../../../../Templates/Requests/CrudApiFilterRequest.template');
    }

    protected function generateResource($name)
    {
        $this->files['Resources'][$name . 'Resource'] = $this->generateStandartFile([['{%MODULENAME%}'], [$name]], __DIR__ . '../../../../Templates/Resources/CrudApiResource.template');
        $this->files['Resources'][$name . 'FailResource'] = $this->generateStandartFile([['{%MODULENAME%}'], [$name]], __DIR__ . '../../../../Templates/Resources/CrudApiFailResource.template');
    }

    protected function generateProvider($name)
    {
        $this->files['Providers'][$name . 'ServiceProvider'] = $this->generateStandartFile([['{%MODULENAME%}'], [$name]], __DIR__ . '../../../../Templates/Providers/CrudApiServiceProvider.template');
    }

    protected function generateHelper($name)
    {
        $this->files['Helpers']['FilterHelper'] = $this->generateStandartFile([['{%MODULENAME%}'], [$name]], __DIR__ . '../../../../Templates/Helpers/FilterHelper.template');
    }

    protected function generateRoute($name)
    {
        $this->files['routes']['api'] = $this->generateStandartFile([['{%MODULENAME%}', '{%MODULENAMELOWER%}'], [$name, strtolower($name)]], __DIR__ . '../../../../Templates/routes/api.template');
    }

    protected function generateStandartFile($replaces, $template)
    {
        return str_replace($replaces[0], $replaces[1], file_get_contents($template));
    }

    protected function resolveColumnType($column)
    {
        $defaultTypes = [
            'pgsql' => [
                'boolean' => ['bool'],
                'date' => ['date'],
                'datetime' => ['datetime'],
                'float' => ['float4', 'float8'],
                'hashed' => ['hashed'],
                'integer' => ['int2', 'int4', 'int8'],
                'object' => ['json'],
                'string' => ['varchar', 'text'],
                'timestamp' => ['timestamp']
            ]
        ];
        if (strstr($column->column_name, 'Password') || strstr($column->column_name, 'password') || strstr($column->column_name, 'PASSWORD'))
            return 'hashed';

        foreach ($defaultTypes[Config::get('database.default')] as $k => $v) {
            foreach ($v as $v2) {
                if ($column->udt_name == $v2)
                    return $k;
            }
        }
        return 'string';
    }
}
