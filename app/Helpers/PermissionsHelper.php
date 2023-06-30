<?php

namespace App\Helpers;

use App\Models\Permission;

class PermissionsHelper
{
    public static function getAllPermissions()
    {
        return collect(static::adminPermissions());
        // ->mergeRecursive(static::superAdminPermissions());
    }

    public static function getAdminPermissionsData(): array
    {
        $persmissions = self::adminPermissions();

        $data = [];
        foreach ($persmissions as $key => $persmission) {
            if (is_array($persmission)) {
                $data[] = $key;
                foreach ($persmission as $key => $persmission) {
                    if (is_array($persmission)) {
                        $data[] = $key;

                        foreach ($persmission as $p) {
                            $data[] = $p;
                        }
                    } else {
                        $data[] = $persmission;
                    }
                }
            } else {
                $data[] = $persmission;
            }
        }
        return $data;
    }

    public static function adminPermissions(): array
    {
        return [
            'user_access' => [
                'user_create',
                'user_edit',
                'user_delete',
            ],
            'user_discount_access' => [
                'user_discount_create',
                'user_discount_edit',
                'user_discount_delete',
            ],
            'role_access' => [
                'role_create',
                'role_edit',
                'role_delete',
            ],
            'permission_access' => [
                'permission_create',
                'permission_edit',
                'permission_delete',
            ],

            'product_access' => [
                'product_create',
                'product_edit',
                'product_delete',
            ],
            'product_category_access' => [
                'product_category_create',
                'product_category_edit',
                'product_category_delete',
            ],
            'product_brand_access' => [
                'product_brand_create',
                'product_brand_edit',
                'product_brand_delete',
            ],
            'product_unit_access' => [
                'product_unit_create',
                'product_unit_edit',
                'product_unit_delete',
            ],

            'supplier_access' => [
                'supplier_create',
                'supplier_edit',
                'supplier_delete',
            ],

            'warehouse_access' => [
                'warehouse_create',
                'warehouse_edit',
                'warehouse_delete',
            ],

            'uom_access' => [
                'uom_create',
                'uom_edit',
                'uom_delete',
            ],

            'receive_order_access' => [
                'receive_order_create',
                'receive_order_edit',
                'receive_order_delete',
                'receive_order_done',
            ],

            'sales_order_access' => [
                'sales_order_create',
                'sales_order_edit',
                'sales_order_delete',
                'sales_order_print',
                'sales_order_export_xml',
                'receive_order_verify_access',
            ],

            'delivery_order_access' => [
                'delivery_order_create',
                'delivery_order_edit',
                'delivery_order_delete',
                'delivery_order_print',
                'delivery_order_done',
            ],

            'stock_access' => [
                'stock_create',
                'stock_edit',
                'stock_delete',
                'stock_grouping',
                'stock_print',
            ],

            'stock_opname_access' => [
                'stock_opname_create',
                'stock_opname_edit',
                'stock_opname_delete',
                'stock_opname_done',
            ],

            'stock_history_access' => [
                'stock_history_create',
                'stock_history_edit',
                'stock_history_delete',
                'stock_history_done',
            ],

            'adjustment_request_access' => [
                'adjustment_request_create',
                'adjustment_request_edit',
                'adjustment_request_delete',
                'adjustment_request_approve',
            ],

            'product_unit_blacklist_access' => [
                'product_unit_blacklist_create',
                'product_unit_blacklist_delete',
            ],

            'setting_access' => [
                'setting_edit',
            ],
        ];
    }

    // public static function superAdminPermissions(): array
    // {
    //     return [];
    // }

    public static function generateChilds(Permission $headSubPermissions, array $subPermissions)
    {
        $guard = 'web';
        collect($subPermissions)->each(function ($permission, $key) use ($headSubPermissions, $guard) {
            if (is_array($permission)) {
                $hsp = Permission::firstOrCreate([
                    'name' => $key,
                    'guard_name' => $guard,
                    'parent_id' => $headSubPermissions->id
                ]);

                self::generateChilds($hsp, $permission);
            } else {
                $hsp = Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => $guard,
                    'parent_id' => $headSubPermissions->id
                ]);
            }

            return;
        });
    }
}
