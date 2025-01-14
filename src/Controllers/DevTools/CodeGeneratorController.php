<?php

namespace Slowlyo\SlowAdmin\Controllers\DevTools;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Slowlyo\SlowAdmin\Renderers\Page;
use Slowlyo\SlowAdmin\Renderers\Form;
use Slowlyo\SlowAdmin\Renderers\Card;
use Slowlyo\SlowAdmin\Renderers\Flex;
use Slowlyo\SlowAdmin\Renderers\Alert;
use Illuminate\Support\Facades\Artisan;
use Slowlyo\SlowAdmin\Renderers\TextControl;
use Slowlyo\SlowAdmin\Renderers\GroupControl;
use Slowlyo\SlowAdmin\Renderers\TableControl;
use Slowlyo\SlowAdmin\Renderers\SelectControl;
use Slowlyo\SlowAdmin\Renderers\VanillaAction;
use Slowlyo\SlowAdmin\Renderers\CheckboxControl;
use Slowlyo\SlowAdmin\Renderers\FieldSetControl;
use Slowlyo\SlowAdmin\Controllers\AdminController;
use Slowlyo\SlowAdmin\Renderers\CheckboxesControl;
use Slowlyo\SlowAdmin\Libs\CodeGenerator\ModelGenerator;
use Slowlyo\SlowAdmin\Libs\CodeGenerator\ServiceGenerator;
use Slowlyo\SlowAdmin\Libs\CodeGenerator\MigrationGenerator;
use Slowlyo\SlowAdmin\Libs\CodeGenerator\ControllerGenerator;

class CodeGeneratorController extends AdminController
{
    protected string $queryPath = 'dev_tools/code_generator';

    public static array $dataTypeMap = [
        'int'                => 'integer',
        'int@unsigned'       => 'unsignedInteger',
        'tinyint'            => 'tinyInteger',
        'tinyint@unsigned'   => 'unsignedTinyInteger',
        'smallint'           => 'smallInteger',
        'smallint@unsigned'  => 'unsignedSmallInteger',
        'mediumint'          => 'mediumInteger',
        'mediumint@unsigned' => 'unsignedMediumInteger',
        'bigint'             => 'bigInteger',
        'bigint@unsigned'    => 'unsignedBigInteger',
        'date'               => 'date',
        'time'               => 'time',
        'datetime'           => 'dateTime',
        'timestamp'          => 'timestamp',
        'enum'               => 'enum',
        'json'               => 'json',
        'binary'             => 'binary',
        'float'              => 'float',
        'double'             => 'double',
        'decimal'            => 'decimal',
        'varchar'            => 'string',
        'char'               => 'char',
        'text'               => 'text',
        'mediumtext'         => 'mediumText',
        'longtext'           => 'longText',
    ];

    public function index(): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $page = Page::make()
            ->title(__('admin.code_generator'))
            ->body($this->form())
            ->remark(
                __('admin.code_generators.remark1') .
                "<a href='https://learnku.com/docs/laravel/9.x/migrations/12248#b419dd' target='_blank'>" .
                __('admin.code_generators.remark2') .
                "</a>, " . __('admin.code_generators.remark3')
            );

        return $this->response()->success($page);
    }

    public function form()
    {
        // 下划线的表名处理成驼峰文件名
        $nameHandler =
            'JOIN(ARRAYMAP(SPLIT(IF(ENDSWITH(table_name, "s"), LEFT(table_name, LEN(table_name) - 1), table_name), "_"), item=>CAPITALIZE(item)))';

        return Form::make()
            ->id('code_generator_form')
            ->wrapWithPanel(false)
            ->title(' ')
            ->mode('horizontal')
            ->resetAfterSubmit(true)
            ->api($this->getStorePath())
            ->data([
                'table_info' => $this->getDatabaseColumns(),
            ])
            ->body([
                Card::make()->body(
                    GroupControl::make()->body([
                        GroupControl::make()->direction('vertical')->body([
                            GroupControl::make()->body([
                                TextControl::make()
                                    ->label(__('admin.code_generators.app_title'))
                                    ->name('title')
                                    ->value('${' . $nameHandler . '}'),
                                Flex::make()->justify('end')->items([
                                    VanillaAction::make()
                                        ->type('submit')
                                        ->label(__('admin.code_generators.generate_code'))
                                        ->level('primary')
                                        ->icon('fa-solid fa-terminal'),
                                ]),
                            ]),
                            GroupControl::make()->body([
                                TextControl::make()
                                    ->label(__('admin.code_generators.table_name'))
                                    ->name('table_name')
                                    ->value('')
                                    ->required(true),
                                SelectControl::make()
                                    ->label(__('admin.code_generators.exists_table'))
                                    ->name('exists_table')
                                    ->searchable(true)
                                    ->clearable(true)
                                    ->selectMode('group')
                                    ->options(
                                        $this->getDatabaseColumns()->map(function ($item, $index) {
                                            return [
                                                'label'    => $index,
                                                'children' => $item->keys()->map(function ($item) use ($index) {
                                                    return [
                                                        'value' => $item . '-' . $index,
                                                        'label' => $item,
                                                    ];
                                                }),
                                            ];
                                        })->values()
                                    )
                                    ->onEvent([
                                        'change' => [
                                            'actions' => [
                                                // 更新 table_name 的值
                                                [
                                                    'actionType'  => 'setValue',
                                                    'componentId' => 'code_generator_form',
                                                    'args'        => [
                                                        'value' => [
                                                            'table_name' => '${SPLIT(event.data.value, "-")[0]}',
                                                        ],
                                                    ],
                                                ],
                                                [
                                                    'actionType'  => 'setValue',
                                                    'componentId' => 'code_generator_form',
                                                    'args'        => [
                                                        'value' => [
                                                            'columns' => '${table_info[SPLIT(event.data.value, "-")[1]][SPLIT(event.data.value, "-")[0]]}',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ]),
                            ]),
                            CheckboxesControl::make()
                                ->name('needs')
                                ->label(__('admin.code_generators.options'))
                                ->joinValues(false)
                                ->extractValue(true)
                                ->checkAll(true)
                                ->defaultCheckAll(true)
                                ->options([
                                    [
                                        'label' => __('admin.code_generators.create_database_migration'),
                                        'value' => 'need_database_migration',
                                    ],
                                    [
                                        'label' => __('admin.code_generators.create_table'),
                                        'value' => 'need_create_table',
                                    ],
                                    [
                                        'label' => __('admin.code_generators.create_model'),
                                        'value' => 'need_model',
                                    ],
                                    [
                                        'label' => __('admin.code_generators.create_controller'),
                                        'value' => 'need_controller',
                                    ],
                                    [
                                        'label' => __('admin.code_generators.create_service'),
                                        'value' => 'need_service',
                                    ],
                                ]),
                            FieldSetControl::make()
                                ->title(__('admin.code_generators.expand_more_settings'))
                                ->collapseTitle(__('admin.code_generators.collapse_settings'))
                                ->collapsable(true)
                                ->collapsed(true)
                                ->titlePosition('bottom')->body([
                                    TextControl::make()
                                        ->label(__('admin.code_generators.primary_key'))
                                        ->name('primary_key')
                                        ->value('id')
                                        ->description(__('admin.code_generators.primary_key_description'))
                                        ->required(true),
                                    TextControl::make()
                                        ->label(__('admin.code_generators.model_name'))
                                        ->name('model_name')
                                        ->value($this->getNamespace('Models', 1) . '${' . $nameHandler . '}'),
                                    TextControl::make()
                                        ->label(__('admin.code_generators.controller_name'))
                                        ->name('controller_name')
                                        ->value($this->getNamespace('Controllers') . '${' . $nameHandler . '}Controller'),
                                    TextControl::make()
                                        ->label(__('admin.code_generators.service_name'))
                                        ->name('service_name')
                                        ->value($this->getNamespace('Services',
                                                1) . '${' . $nameHandler . '}Service'),
                                    CheckboxControl::make()
                                        ->name('need_timestamps')
                                        ->option('CreatedAt & UpdatedAt')
                                        ->value(1),
                                    CheckboxControl::make()
                                        ->name('soft_delete')
                                        ->option(__('admin.soft_delete'))
                                        ->value(1),
                                ]),
                        ]),
                    ]),
                ),

                Card::make()->body([
                    Alert::make()
                        ->body("如果字段名存在 no、status 会导致 form 回显失败! <a href='https://slowlyo.gitee.io/slow-admin-doc/#/docs/issue?id=%f0%9f%90%9b-%e7%bc%96%e8%be%91-%e8%af%a6%e6%83%85%e9%a1%b5%e9%9d%a2%e6%95%b0%e6%8d%ae%e5%9b%9e%e6%98%be%e5%a4%b1%e8%b4%a5' target='_blank'>查看详情</a> ")
                        ->level('warning')
                        ->showCloseButton(true)
                        ->showIcon(true),
                    TableControl::make()
                        ->name('columns')
                        ->label(false)
                        ->addable(true)
                        ->needConfirm(false)
                        ->draggable(true)
                        ->removable(true)
                        ->columnsTogglable(false)
                        ->value([
                            [
                                'name'       => '',
                                'type'       => 'string',
                                'additional' => 255,
                                'index'      => '',
                            ],
                        ])
                        ->columns([
                            TextControl::make()
                                ->name('name')
                                ->label(__('admin.code_generators.column_name'))
                                ->required(true),
                            SelectControl::make()
                                ->name('type')
                                ->label(__('admin.code_generators.type'))
                                ->options($this->availableFieldTypes())
                                ->searchable(true)
                                ->value('string')
                                ->required(true),
                            TextControl::make()
                                ->name('additional')
                                ->label(__('admin.code_generators.extra_params'))
                                ->width(160)
                                ->size('sm'),
                            CheckboxControl::make()
                                ->name('nullable')
                                ->label(__('admin.code_generators.nullable'))
                                ->width(60),
                            SelectControl::make()
                                ->name('index')
                                ->label(__('admin.code_generators.index'))
                                ->size('xs')
                                ->width(90)
                                ->options(
                                    collect(['index', 'unique'])->map(fn($value) => [
                                        'label' => $value,
                                        'value' => $value,
                                    ]))
                                ->clearable(true),
                            TextControl::make()->name('comment')->label(__('admin.code_generators.comment')),
                            TextControl::make()
                                ->name('default')
                                ->label(__('admin.code_generators.default_value')),
                        ]),
                ]),
            ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\JsonResource
    {
        $needs   = collect($request->needs);
        $columns = collect($request->columns);

        $paths   = [];
        $message = '';
        try {
            if ($needs->contains('need_model')) {
                $path = ModelGenerator::make()
                    ->primary($request->primary_key)
                    ->timestamps($request->get('need_timestamps', false))
                    ->softDelete($request->get('soft_delete', false))
                    ->generate($request->table_name, $request->model_name);

                $message .= "Model generation succeeded!\n";
                $message .= $path . "\n\n";

                $paths[] = $path;
            }

            if ($needs->contains('need_database_migration')) {
                $path = MigrationGenerator::make()
                    ->primary($request->primary_key)
                    ->timestamps($request->get('need_timestamps', false))
                    ->softDelete($request->get('soft_delete', false))
                    ->generate($request->table_name, $columns);

                $message .= "Migration generation succeeded!\n";
                $message .= $path . "\n\n";

                $paths[] = $path;
            }

            if ($needs->contains('need_controller')) {
                $path = ControllerGenerator::make()
                    ->primary($request->primary_key)
                    ->title($request->title)
                    ->tableName($request->table_name)
                    ->serviceName($request->service_name)
                    ->columns($columns)
                    ->timestamps($request->get('need_timestamps', false))
                    ->generate($request->controller_name);

                $message .= "Controller generation succeeded!\n";
                $message .= $path . "\n\n";

                $paths[] = $path;
            }

            if ($needs->contains('need_service')) {
                $path = ServiceGenerator::make()->generate($request->service_name, $request->model_name);

                $message .= "Service generation succeeded!\n";
                $message .= $path . "\n\n";

                $paths[] = $path;
            }

            if ($needs->contains('need_create_table')) {
                Artisan::call('migrate');
                $message .= Artisan::output();
            }
        } catch (\Exception $e) {
            app('files')->delete($paths);

            return $this->response()->fail($e->getMessage());
        }

        return $this->response()->successMessage($message);
    }

    public function availableFieldTypes(): array
    {
        return collect([
            'bigIncrements',
            'bigInteger',
            'binary',
            'boolean',
            'char',
            'dateTimeTz',
            'dateTime',
            'date',
            'decimal',
            'double',
            'enum',
            'float',
            'foreignId',
            'foreignIdFor',
            'foreignUuid',
            'geometryCollection',
            'geometry',
            'id',
            'increments',
            'integer',
            'ipAddress',
            'json',
            'jsonb',
            'lineString',
            'longText',
            'macAddress',
            'mediumIncrements',
            'mediumInteger',
            'mediumText',
            'morphs',
            'multiLineString',
            'multiPoint',
            'multiPolygon',
            'nullableMorphs',
            'nullableTimestamps',
            'nullableUuidMorphs',
            'point',
            'polygon',
            'rememberToken',
            'set',
            'smallIncrements',
            'smallInteger',
            'softDeletesTz',
            'softDeletes',
            'string',
            'text',
            'timeTz',
            'time',
            'timestampTz',
            'timestamp',
            'timestampsTz',
            'timestamps',
            'tinyIncrements',
            'tinyInteger',
            'tinyText',
            'unsignedBigInteger',
            'unsignedDecimal',
            'unsignedInteger',
            'unsignedMediumInteger',
            'unsignedSmallInteger',
            'unsignedTinyInteger',
            'uuidMorphs',
            'uuid',
            'year',
        ])->map(fn($value) => ['label' => $value, 'value' => $value])->toArray();
    }

    public function getNamespace($name, $app = null): string
    {
        $namespace = collect(explode('\\', config('admin.route.namespace')));

        $namespace->pop();

        if ($app) {
            $namespace->pop();
        }

        return $namespace->push($name)->implode('/') . '/';
    }

    protected function getDatabaseColumns($db = null, $tb = null)
    {
        $databases = Arr::where(config('database.connections', []), function ($value) {
            $supports = ['mysql'];

            return in_array(strtolower(Arr::get($value, 'driver')), $supports);
        });

        $data = [];

        try {
            foreach ($databases as $connectName => $value) {
                if ($db && $db != $value['database']) {
                    continue;
                }

                $sql = sprintf('SELECT * FROM information_schema.columns WHERE table_schema = "%s"',
                    $value['database']);

                if ($tb) {
                    $p = Arr::get($value, 'prefix');

                    $sql .= " AND TABLE_NAME = '{$p}{$tb}'";
                }

                $sql .= ' ORDER BY `ORDINAL_POSITION` ASC';

                $tmp = DB::connection($connectName)->select($sql);

                $collection = collect($tmp)->map(function ($v) use ($value) {
                    if (!$p = Arr::get($value, 'prefix')) {
                        return (array)$v;
                    }
                    $v = (array)$v;

                    $v['TABLE_NAME'] = Str::replaceFirst($p, '', $v['TABLE_NAME']);

                    return $v;
                });

                $data[$value['database']] = $collection->groupBy('TABLE_NAME')->map(function ($v) {
                    return collect($v)->keyBy('COLUMN_NAME')->where('COLUMN_NAME', '<>', 'id')->map(function ($v) {
                        $v['COLUMN_TYPE'] = strtolower($v['COLUMN_TYPE']);
                        $v['DATA_TYPE']   = strtolower($v['DATA_TYPE']);

                        if (Str::contains($v['COLUMN_TYPE'], 'unsigned')) {
                            $v['DATA_TYPE'] .= '@unsigned';
                        }


                        return [
                            'name'     => $v['COLUMN_NAME'],
                            'type'     => Arr::get(self::$dataTypeMap, $v['DATA_TYPE'], 'string'),
                            'default'  => $v['COLUMN_DEFAULT'],
                            'nullable' => $v['IS_NULLABLE'] == 'YES',
                            'comment'  => $v['COLUMN_COMMENT'],
                        ];
                    })->values();
                });
            }
        } catch (\Throwable $e) {
        }

        return collect($data);
    }
}
