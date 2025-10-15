<?php
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// api to fetch all registered products for external requests

// $router->get('stabilize-delivery-trip-to-waybill-relationship', 'Invoice\InvoicesController@stabilizeDeliveryTripToWaybillRelationship');
$router->get('get-warehouse-products', 'ApiController@warehouseProducts');
// $router->get('delete-restored-invoices', 'Invoice\InvoicesController@deleteRestoredInvoices');

$router->post('receive-rep-stock', 'ApiController@sendRepStock');
$router->put('mark-stock-as-received/{dispatchProduct}', 'ApiController@flagSupplyAsReceived');

$router->get('check-already-received-stock', 'ApiController@checkAlreadyReceivedStock');

$router->get('fetch-reps-details', 'ApiController@fetchRepsForTransferToSalesApp');
// $router->post('fetch-reps-details', 'ApiController@fetchRepsForTransferToSalesApp');
// $router->get('resolve-incomplete-supplies', 'Controller@resolveIncompleteSupplies');
// $router->get('partial-invoices', 'Controller@normalizePartialSuppliedInvoices');
$router->get('hello-message', function () {
    return response()->json(['message' => 'Hello World'], 200);
});

// Publicly accessible api
$router->group(['prefix' => 'report'], function () use ($router) {
    $router->get('customers', 'ApiController@customers');
    $router->get('instant-balances', 'ApiController@instantBalances');
    $router->get('unsupplied-invoices', 'ApiController@unsuppliedInvoices');
    $router->get('all-generated-invoices', 'ApiController@allInvoicesRaised');

    $router->get('products-in-stock', 'ApiController@productInStock');
    $router->get('expired-products', 'ApiController@expiredProducts');
    $router->get('returned-products', 'ApiController@returnedProducts');
    $router->get('stock-counts', 'ApiController@stockCount');
});


$router->post('auth/login', 'AuthController@login');
// $router->get('dispatch-product/issue', 'DebugController@solveDispatchProductIssue');
// $router->get('update-dispatch-product-invoice', 'DebugController@updateDispatchProductInvoiceId');

// $router->get('total-product', 'DebugController@totalDispatchedProduct');
// $router->get('reserved-', 'DebugController@reservedProducts');
// $router->get('stock-balance', 'DebugController@balanceStockAccount');
// $router->get('stock-balance-product', 'DebugController@balanceStockAccountPerProduct');
// $router->get('balance-invoice-items', 'DebugController@balanceInvoiceItems');
// $router->get('stabilize-account', 'DeproductbugController@stabilizeAccount');
$router->get('reset', 'DebugController@resetStock');
// $router->get('split', 'DebugController@splitExcessStock');
// $router->get('stabilize-invoice-items', 'Invoice\InvoicesController@stabilizeInvoiceItems');
// $router->get('deliver-items', 'Invoice\InvoicesController@deliverProducts');
// $router->get('correct-dispatch-product', 'Invoice\InvoicesController@correctDispatchProductDate');
// $router->get('invoice-items-without-waybill', 'Invoice\InvoicesController@checkInvoiceItemsWithoutWaybill');
// $router->get('set-transfer-request-warehouse', 'Transfers\GoodsTransferController@setTransferRequestWarehouse');

$router->get('clear-partial-invoices', 'Invoice\InvoicesController@clearPartialInvoices');
$router->group(['middleware' => 'auth:api'], function () use ($router) {

    $router->get('auth/user', 'AuthController@user');
    $router->post('auth/logout', 'AuthController@logout');
    $router->group(['middleware' => ['permission:manage user']], function () use ($router) {
        $router->get('users', 'UserController@index'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->post('users', 'UserController@store'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->post('users/add-bulk-customers', 'UserController@addBulkCustomers'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);

        $router->get('users/{user}', 'UserController@show'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->put('users/reset-password/{user}', 'UserController@adminResetUserPassword'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);

        $router->delete('users/{user}', 'UserController@destroy'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->delete('customers/{user}', 'UserController@destroyCustomer'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->get('users/{user}/permissions', 'UserController@permissions'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->put('users/{user}/permissions', 'UserController@updatePermissions'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->apiResource('roles', 'RoleController'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->get('roles/{role}/permissions', 'RoleController@permissions'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
        $router->apiResource('permissions', 'PermissionController'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);
    });

    $router->get('user-notifications', 'UserController@userNotifications');


    $router->put('users/{user}', 'UserController@update');
    $router->put('users/assign-role/{user}', 'UserController@assignRole');

    $router->put('users/change-customer-details/{customer}', 'UserController@changeCustomerDetails');
    $router->put('users/update-password/{user}', 'UserController@updatePassword');


    // $router->apiResource('users', 'UserController'); //->middleware('permission:' . \App\Laravue\Acl::PERMISSION_USER_MANAGE);


    $router->get('fetch-necessary-params', 'Controller@fetchNecessayParams');
    $router->get('fetch-customers', 'Controller@fetchCustomers');
    $router->post('upload-file', 'Controller@uploadFile');
    $router->group(['prefix' => 'ticket'], function () use ($router) {
        //customer
        $router->get('/', 'IssueTicketsController@index');
        $router->get('pending-tickets', 'IssueTicketsController@pendingTickets');
        $router->get('my-tickets', 'IssueTicketsController@myTickets');
        $router->post('create-ticket', 'IssueTicketsController@createTicket');
        $router->post('approve-ticket', 'IssueTicketsController@approveTicket');
        $router->delete('delete-ticket/{ticket}', 'IssueTicketsController@deleteTicket');
    });
    $router->group(['prefix' => 'dashboard'], function () use ($router) {
        //customer
        $router->group(['prefix' => 'admin'], function () use ($router) {
            $router->get('/', 'DashboardController@adminDashboard'); //->middleware('permission:view admin dashboard');

        });
        //driver
        $router->group(['prefix' => 'driver'], function () use ($router) {
        });
    });
    //////////////////////////////REPORTS//////////////////////////////
    $router->group(['prefix' => 'reports'], function () use ($router) {
        //customer
        $router->group(['prefix' => 'graphical'], function () use ($router) {
            $router->get('products-in-stock', 'ReportsController@productsInStockGraph')->middleware('permission:view reports');
            $router->get('reports-on-vehicle', 'ReportsController@reportsOnVehiclesGraph')->middleware('permission:view reports');
            $router->get('reports-on-waybill', 'ReportsController@reportsOnWaybillGraph')->middleware('permission:view reports');
        });
        //driver
        $router->group(['prefix' => 'tabular'], function () use ($router) {
            $router->get('products-in-stock', 'ReportsController@productsInStockTabular')->middleware('permission:view reports');
            $router->get('outbounds', 'ReportsController@outbounds')->middleware('permission:view reports');
            $router->get('unsupplied', 'ReportsController@unsuppliedInvoices')->middleware('permission:view reports');
            $router->get('all-untreated-invoices', 'ReportsController@allUntreatedInvoices');
            $router->get('all-partially-treated-invoices', 'ReportsController@allPartiallyTreatedInvoices');

            $router->get('all-invoice-raised', 'ReportsController@allInvoicesRaised');
            $router->get('all-waybilled-invoices', 'ReportsController@allWaybilledInvoices');
        });
        $router->get('audit-trails', 'ReportsController@auditTrails')->middleware('permission:view reports');
        $router->get('notification/mark-as-read', 'ReportsController@markAsRead');
        $router->get('backups', 'ReportsController@backUps'); //->middleware('permission:backup database');
        $router->get('bin-card', 'ReportsController@fetchBinCard');
        $router->get('instant-balances', 'ReportsController@instantBalances');

        $router->get('reserved-product-transactions/{item_in_stock}', 'ReportsController@reservedProductTransactions');
    });
    ////////////////////////////////////////////////////////////////////////////////////////
    $router->group(['prefix' => 'user'], function () use ($router) {
        //customer
        $router->group(['prefix' => 'customer'], function () use ($router) {
            $router->post('/store', 'UserController@addCustomer')->middleware('permission:create order');
        });
        //driver
        $router->group(['prefix' => 'driver'], function () use ($router) {
            $router->post('/store', 'UserController@addDriver')->middleware('permission:manage drivers');
            $router->put('update/{driver}', 'UserController@updateDriver');
        });
    });
    $router->group(['prefix' => 'order', 'namespace' => 'Order'], function () use ($router) {
        $router->group(['prefix' => 'general'], function () use ($router) {

            $router->get('/', 'OrdersController@index')->middleware('permission:view order');
            $router->get('show/{order}', 'OrdersController@show')->middleware('permission:view order');

            $router->post('store', 'OrdersController@store')->middleware('permission:create order');

            $router->put('approve/{order}', 'OrdersController@changeOrderStaus')->middleware('permission:approve order');

            $router->put('deliver/{order}', 'OrdersController@changeOrderStaus')->middleware('permission:deliver order|approve order');

            $router->put('cancel/{order}', 'OrdersController@changeOrderStaus')->middleware('permission:cancel order');

            $router->put('assign-order-to-warehouse/{order}', 'OrdersController@assignOrderToWarehouse')->middleware('permission:assign order to warehouse');
        });
    });
    $router->group(['prefix' => 'invoice', 'namespace' => 'Invoice'], function () use ($router) {
        $router->group(['prefix' => 'general'], function () use ($router) {


            $router->get('/', 'InvoicesController@index')->middleware('permission:view invoice');
            $router->get('show/{invoice}', 'InvoicesController@show')->middleware('permission:view invoice');
            $router->get('fetch-pending-invoices', 'InvoicesController@fetchPendingInvoices');

            $router->post('store', 'InvoicesController@store')->middleware('permission:create invoice');
            $router->post('check-product-quantity-in-stock', 'InvoicesController@checkProductQuantityInStock')->middleware('permission:create invoice');

            $router->post('bulk-upload', 'InvoicesController@bulkUpload')->middleware('permission:create invoice');

            $router->get('edit/{invoice}', 'InvoicesController@edit')->middleware('permission:update invoice');
            $router->put('update/{invoice}', 'InvoicesController@update')->middleware('permission:update invoice');

            $router->delete('delete/{invoice}', 'InvoicesController@destroy')->middleware('permission:delete invoice');
            $router->post('archive-invoices', 'InvoicesController@archiveInvoices')->middleware('permission:archive invoices');
            $router->post('restore-archived-invoices', 'InvoicesController@restoreArchivedInvoices')->middleware('permission:archive invoices');

            $router->put('assign-invoice-to-warehouse/{invoice}', 'InvoicesController@assignInvoiceToWarehouse')->middleware('permission:assign invoice to warehouse');

            $router->put('reverse-untreated-invoice-item/{invoice_item}', 'InvoicesController@reverseUnTreatedInvoiceItem'); //->middleware('permission:assign invoice to warehouse');
            $router->put('reverse-partially-treated-invoice-item/{invoice_item}', 'InvoicesController@reversePartiallyTreatedInvoiceItem');
        });
        $router->group(['prefix' => 'waybill'], function () use ($router) {
            $router->get('/', 'InvoicesController@waybills')->middleware('permission:view waybill|manage waybill');
            $router->get('show/{waybill}', 'InvoicesController@showWaybill')->middleware('permission:view waybill|manage waybill');

            $router->get('undelivered-invoices', 'InvoicesController@unDeliveredInvoices')->middleware('permission:generate waybill|manage waybill');
            $router->get('undelivered-invoices-search', 'InvoicesController@unDeliveredInvoicesSearch')->middleware('permission:generate waybill|manage waybill');
            $router->get('waybill-selected-invoices', 'InvoicesController@waybillSelectedInvoices')->middleware('permission:generate waybill|manage waybill');

            $router->get('expenses', 'InvoicesController@waybillExpenses')->middleware('permission:manage waybill cost');

            $router->get('delivery-trips-for-extra-cost', 'InvoicesController@deliveryTripsForExtraCost')->middleware('permission:manage waybill cost');

            $router->post('add-extra-delivery-cost', 'InvoicesController@addExtraDeliveryCost')->middleware('permission:manage waybill cost');

            $router->post('add-waybill-expenses', 'InvoicesController@addWaybillExpenses')->middleware('permission:manage waybill cost');

            $router->post('detach-waybill-from-trip', 'InvoicesController@detachWaybillFromTrip')->middleware('permission:manage waybill cost');

            $router->post('add-waybill-to-trip', 'InvoicesController@addWaybillToTrip')->middleware('permission:manage waybill cost');

            $router->post('change-trip-vehicle', 'InvoicesController@changeTripVehicle')->middleware('permission:manage waybill cost');

            $router->delete('delete/{waybill}', 'InvoicesController@deleteWaybill')->middleware('permission:delete pending waybill');

            $router->group(['middleware' => 'permission:manage waybill|generate waybill'], function () use ($router) {

                $router->get('edit/{waybill}', 'InvoicesController@editWaybill');
                $router->get('undelivered-invoices', 'InvoicesController@unDeliveredInvoices');
                $router->get('fetch-available-vehicles', 'InvoicesController@fetchAvailableVehicles');
                $router->post('store', 'InvoicesController@generateWaybill');
                $router->put('update/{waybill_id}', 'InvoicesController@updateWaybill');
                $router->put('change-status/{waybill}', 'InvoicesController@changeWaybillStatus');
            });
        });
    });
    $router->group(['prefix' => 'transfers', 'namespace' => 'Transfers'], function () use ($router) {
        $router->group(['middleware' => 'permission:manage transfer request'], function () use ($router) {
            $router->group(['prefix' => 'general'], function () use ($router) {
                $router->get('/', 'GoodsTransferController@index');
                $router->get('show/{transfer_request}', 'GoodsTransferController@show');

                $router->post('store', 'GoodsTransferController@store');

                $router->put('update/{transfer_request}', 'GoodsTransferController@update');

                $router->delete('delete/{transfer_request}', 'GoodsTransferController@destroy');

                $router->put('assign-invoice-to-warehouse/{invoice}', 'GoodsTransferController@assignInvoiceToWarehouse');
            });
            $router->group(['prefix' => 'waybill'], function () use ($router) {
                $router->get('/', 'GoodsTransferController@waybills');
                $router->get('undelivered-invoices', 'GoodsTransferController@unDeliveredInvoices');

                $router->get('expenses', 'GoodsTransferController@waybillExpenses');

                $router->get('delivery-trips-for-extra-cost', 'GoodsTransferController@deliveryTripsForExtraCost');

                $router->post('add-extra-delivery-cost', 'GoodsTransferController@addExtraDeliveryCost');

                $router->post('add-waybill-expenses', 'GoodsTransferController@addWaybillExpenses');

                $router->post('detach-waybill-from-trip', 'GoodsTransferController@detachWaybillFromTrip');

                $router->post('add-waybill-to-trip', 'GoodsTransferController@addWaybillToTrip');



                $router->get('undelivered-invoices', 'GoodsTransferController@unDeliveredInvoices');
                $router->get('fetch-available-vehicles', 'GoodsTransferController@fetchAvailableVehicles');
                $router->post('store', 'GoodsTransferController@generateWaybill');
                $router->put('change-status/{waybill}', 'GoodsTransferController@changeWaybillStatus');
                $router->post('set-dispatcher', 'GoodsTransferController@setWaybillDispatcher');
            });
        });
    });
    $router->group(['prefix' => 'audit'], function () use ($router) {
        $router->group(['prefix' => 'confirm'], function () use ($router) {
            $router->group(['middleware' => 'permission:audit confirm actions'], function () use ($router) {
                $router->put('/waybill/{waybill}', 'AuditConfirmsController@confirmWaybill');

                $router->put('/transfer-waybill/{waybill}', 'AuditConfirmsController@confirmTransferWaybill');
                $router->put('/items-in-stock/{item_stock_sub_batch}', 'AuditConfirmsController@confirmStockedItems');

                $router->put('/returned-products/{returned_product}', 'AuditConfirmsController@confirmReturnedItems');

                $router->put('/delivery-cost/{delivery_cost_expense}', 'AuditConfirmsController@confirmDeliveryCost');

                $router->put('/vehicle-expenses/{vehicle_expense}', 'AuditConfirmsController@confirmVehicleExpense');

                $router->put('/invoice/{invoice}', 'AuditConfirmsController@confirmInvoice');
            });
        });
    });
    $router->group(['prefix' => 'setting', 'namespace' => 'Setting'], function () use ($router) {

        $router->group(['prefix' => 'tax'], function () use ($router) {
            $router->get('/', 'TaxesController@index');

            $router->group(['middleware' => 'permission:manage tax'], function () use ($router) {
                $router->post('store', 'TaxesController@store');
                $router->put('update/{tax}', 'TaxesController@update');
                $router->delete('delete/{tax}', 'TaxesController@destroy');
            });
        });

        $router->group(['prefix' => 'currency'], function () use ($router) {
            $router->get('/', 'CurrenciesController@index');
            $router->group(['middleware' => 'permission:manage currency'], function () use ($router) {
                $router->post('store', 'CurrenciesController@store');
                $router->put('update/{currency}', 'CurrenciesController@update');
                $router->delete('delete/{currency}', 'CurrenciesController@destroy');
            });
        });
    });

    /////////////////////////STOCKS MODULE////////////////////////////
    $router->group(['prefix' => 'stock', 'namespace' => 'Stock'], function () use ($router) {
        /////////////////////////////general stock////////////////////////
        $router->group(['prefix' => 'general-items'], function () use ($router) {

            $router->group(['middleware' => ['permission:view general items|manage general items']], function () use ($router) {
                $router->get('/', 'ItemsController@index')->middleware('permission:view general items|manage general items');
            });
            $router->group(['middleware' => ['permission:manage general items']], function () use ($router) {

                $router->post('store', 'ItemsController@store');
                $router->put('enable-disable/{item}', 'ItemsController@enableOrDisableItem');

                $router->put('update/{item}', 'ItemsController@update');
                $router->delete('delete/{item}', 'ItemsController@destroy');
                $router->get('delete-item-tax', 'ItemsController@destroyItemTax');
                $router->group(['prefix' => 'prices'], function () use ($router) {
                    $router->post('store', 'ItemPricesController@store');
                    $router->put('update/{item_price}', 'ItemPricesController@update');
                    $router->delete('delete/{item_price}', 'ItemPricesController@destroy');
                });
            });
        });
        ///////////////////manage stock//////////////////////////////////
        $router->group(['prefix' => 'items-in-stock'], function () use ($router) {

            $router->get('product-batches', 'ItemStocksController@productBatches');
            $router->get('product-stock-balance-by-expiry-date', 'ItemStocksController@productStockBalanceByExpiryDate');

            $router->get('/', 'ItemStocksController@index')->middleware('permission:view item stocks|manage item stocks');

            $router->get('product-batches', 'ItemStocksController@productBatches');

            //$router->group(['middleware' => 'permission:manage item stocks'], function () use ($router) {
            $router->post('store', 'ItemStocksController@store')->middleware('permission:create item stocks|manage item stocks');
            $router->post('bulk-upload', 'ItemStocksController@uploadBulkProductsInStock')->middleware('permission:create item stocks|manage item stocks');

            $router->put('update/{item_in_stock}', 'ItemStocksController@update')->middleware('permission:update item stocks|manage item stocks');

            $router->post('move-expired-products', 'ItemStocksController@moveExpiredProducts');


            $router->delete('delete/{item_sub_stock}', 'ItemStocksController@destroy')->middleware('permission:delete item stocks|manage item stocks');
            //});

        });
        $router->group(['prefix' => 'count'], function () use ($router) {

            $router->get('/', 'ItemStocksController@fetchStockCounts');
            $router->post('prepare', 'ItemStocksController@prepareStockCount');
            $router->post('save', 'ItemStocksController@saveStockCount');
            $router->put('save-count/{stock_count}', 'ItemStocksController@countStock');
        });
        ///////////////////manage item Category//////////////////////////////////
        $router->group(['prefix' => 'item-category'], function () use ($router) {
            $router->get('/', 'CategoriesController@index')->middleware('permission:view item category|manage item category');

            $router->group(['middleware' => 'permission:manage item category'], function () use ($router) {
                $router->post('store', 'CategoriesController@store');
                $router->put('update/{category}', 'CategoriesController@update');
                $router->delete('delete/{category}', 'CategoriesController@destroy');
            });
        });

        ///////////////////manage returned products//////////////////////////////////
        $router->group(['prefix' => 'returns'], function () use ($router) {
            $router->get('/fetch-delivered-invoices', 'ReturnsController@fetchDeliveredInvoices')->middleware('permission:manage returned products');
            $router->get('/fetch-delivered-invoices-with-returns', 'ReturnsController@fetchDeliveredInvoicesWithReturns')->middleware('permission:manage returned products');

            $router->get('/', 'ReturnsController@index')->middleware('permission:view returned products|manage returned products');
            $router->get('/approved', 'ReturnsController@approvedReturnedProducts')->middleware('permission:view returned products|manage returned products');

            $router->group(['middleware' => 'permission:manage returned products'], function () use ($router) {
                $router->get('fetch-product-batches', 'ReturnsController@fetchProductBatches');
                $router->post('store', 'ReturnsController@store');
                $router->put('update/{stockReturn}', 'ReturnsController@update');
                $router->delete('delete/{returned_product}', 'ReturnsController@destroy');
            });
            $router->post('approve-products', 'ReturnsController@approveReturnedProducts');/*->middleware('permission:approve returned products');*/
            $router->put('approve-all-products/{stockReturn}', 'ReturnsController@approveAllReturnedProducts');
            $router->put('auditor-comment/{stockReturn}', 'ReturnsController@auditorCommentOnReturnedProducts');/*->middleware('permission:approve returned products');*/

        });
    });
    ////////////////////////////////////STOCK ENDS/////////////////////////////////////////////
    ////////////////////////////////////WAREHOUSE/////////////////////////////////////////////
    $router->group(['prefix' => 'warehouse', 'namespace' => 'Warehouse'], function () use ($router) {
        $router->get('fetch-warehouse', 'WarehousesController@index');

        $router->get('/', 'WarehousesController@index')->middleware('permission:view warehouse|manage warehouse');


        $router->group(['middleware' => 'permission:manage warehouse'], function () use ($router) {
            $router->get('/assignable-users', 'WarehousesController@assignableUsers');


            $router->post('store', 'WarehousesController@store');
            $router->put('update/{warehouse}', 'WarehousesController@update');
            $router->delete('delete/{warehouse}', 'WarehousesController@destroy');

            $router->post('add-user-to-warehouse', 'WarehousesController@addUserToWarehouse');
        });
    });
    ////////////////////////////////////WAREHOUSE ENDS/////////////////////////////////////////////

    ////////////////////////////////////LOGISTICS/////////////////////////////////////////////
    $router->group(['prefix' => 'logistics', 'namespace' => 'Logistics'], function () use ($router) {
        $router->group(['prefix' => 'drivers'], function () use ($router) {

            $router->get('/', 'DriversController@index');
            $router->group(['middleware' => 'permission:manage drivers'], function () use ($router) {
                $router->delete('delete/{driver}', 'DriversController@destroy');
            });
        });
        $router->group(['prefix' => 'vehicles'], function () use ($router) {

            $router->get('/', 'VehiclesController@index');
            $router->group(['middleware' => 'permission:manage vehicles'], function () use ($router) {
                $router->post('store', 'VehiclesController@store');
                $router->put('update/{vehicle}', 'VehiclesController@update');
                $router->delete('delete/{vehicle}', 'VehiclesController@destroy');

                $router->get('drivers', 'VehiclesController@vehicleDrivers');
                $router->post('assign-driver', 'VehiclesController@assignDriver');
                $router->delete('unassign-driver/{vehicle_driver}', 'VehiclesController@unAssignDriver');
            });
        });
        $router->group(['prefix' => 'vehicle-types'], function () use ($router) {

            $router->get('/', 'VehicleTypesController@index');
            $router->group(['middleware' => 'permission:manage vehicles'], function () use ($router) {
                $router->post('store', 'VehicleTypesController@store');
                $router->put('update/{vehicleType}', 'VehicleTypesController@update');
                $router->delete('delete/{vehicleType}', 'VehicleTypesController@destroy');
            });
        });
        $router->group(['prefix' => 'vehicle-expenses'], function () use ($router) {

            $router->get('/', 'VehicleExpensesController@index');
            $router->group(['middleware' => 'permission:manage vehicle expenses'], function () use ($router) {
                $router->post('store', 'VehicleExpensesController@store');
                $router->put('approve/{vehicleExpense}', 'VehicleExpensesController@approval')->middleware('permission:approve vehicle expenses');
                $router->post('add-automobile-engineer', 'VehicleExpensesController@addAutomobileEngineer');
                $router->put('update-automobile-engineer/{automobile_engineer}', 'VehicleExpensesController@updateAutomobileEngineer');
                // $router->delete('delete/{vehicleExpense}', 'VehicleExpensesController@destroy');
            });
        });
        $router->group(['prefix' => 'vehicle-conditions'], function () use ($router) {

            $router->get('/', 'VehicleConditionsController@index');
            $router->group(['middleware' => 'permission:manage vehicle conditions'], function () use ($router) {
                $router->post('store', 'VehicleConditionsController@store');
            });
        });
    });
    ////////////////////////////////////LOGISTICS ENDS/////////////////////////////////////////////


});
