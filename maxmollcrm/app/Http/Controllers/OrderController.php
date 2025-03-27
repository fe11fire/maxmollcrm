<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Order;
use App\Models\Stock;
use App\Models\OrderItem;
use App\Models\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Actions\Order\CancelAction;
use App\Actions\Order\ResumeAction;
use App\Services\Enums\OrderStatus;
use App\Actions\Order\CompleteAction;
use App\Http\Requests\OrderFormRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Requests\PutOrderFormRequest;
use App\Http\Requests\PostOrderFormRequest;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function index(OrderFormRequest $request)
    {
        $orders = Order::query()
            ->status($request->safe()->status)
            ->customer($request->safe()->customer);

        return new OrderCollection(
            $orders->paginate($request->safe()->input('per_page', default: 5))
        );
    }

    public function getResume(ResumeAction $action, $id)
    {
        return $action($id);
    }

    public function resume(ResumeAction $action, PutOrderFormRequest $request)
    {
        return $action($request->safe()->id);
    }

    public function getCancel(CancelAction $action, $id)
    {
        return $action($id);
    }

    public function cancel(CancelAction $action, PutOrderFormRequest $request)
    {
        return $action($request->safe()->id);
    }

    public function getComplete(CompleteAction $action, $id)
    {
        return $action($id);
    }

    public function complete(CompleteAction $action, PutOrderFormRequest $request)
    {
        return $action($request->safe()->id);
    }

    public function update(PutOrderFormRequest $request)
    {
        try {
            /** Проводим серию транзакций */
            DB::transaction(function () use ($request) {
                /** 
                 * Получение заказа для обновления
                 */
                $order = Order::findOrFail($request->id);

                /**
                 * Обновить можно только активный заказ
                 */
                if ($order->status != OrderStatus::ACTIVE->value) {
                    throw new Exception('Order not active');
                }

                /**
                 * Если среди данных на обновление есть инфрормация о товарах,
                 * то проводим перерасчет
                 */
                if (($items = $request->safe()->items) !== null) {

                    /**
                     * Исключаем из заказа все товары,
                     * информация по которым не получена.
                     * Т.е. если в запросе в массиве items товар пропущен,
                     * значит его исключаем совсем из заказа
                     */
                    $null_items = OrderItem::whereNotIn('product_id', Arr::pluck($items, 'id'))->where('order_id', $order->id)->get();
                    foreach ($null_items as $order_item) {
                        /**
                         * Возвращаем на склад удаленные из заказа товары
                         * Удаляем соответствующую строку из order_items
                         */
                        Stock::diffStocks($order_item, -$order_item->count);
                        $order_item->delete();
                    }

                    /**
                     * Для товаров items из запроса проводим корректировку количества
                     */
                    foreach ($items as $item) {

                        /**
                         * Получаем список товаров в заказе из items запроса
                         */
                        $order_item = OrderItem::where('product_id', $item['id'])->where('order_id', $order->id)->first();

                        /**
                         * Товар не найден в заказе.
                         * Это новый товар в заказе, создаем его,
                         * но количество товаров будет равно 0.
                         */
                        if ($order_item === null) {
                            $order_item = OrderItem::create([
                                'order_id' => $order->id,
                                'product_id' => $item['id'],
                                'count' => 0,
                            ]);
                        }

                        /**
                         * Корректировка числа товара в заказе.
                         * Получаем значение, которое нужно добавить / убавить.
                         * Если разница равна 0, то количесвто товара в заказе не изменилось, пропускаем блок
                         */
                        if (($difference = ($item['count'] - $order_item->count)) <> 0) {

                            /**
                             * Производим перемещение недостатка товара в заказ со склада
                             * или избытка - из заказа на склад
                             */
                            Stock::diffStocks($order_item, $difference);
                            /**
                             * Обновляем количество товара в заказе
                             */
                            $order_item->update(['count' => DB::raw('count + ' . $difference)]);
                        }
                        
                    }
                }

                /**
                 * Если среди данных на обновление есть Ф.И.О. заказчика,
                 * обновляем его
                 */
                if (($customer = $request->safe()->customer) !== null) {
                    $order->update(['customer' => $customer]);
                }
            });
        } catch (\Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }

    public function create(PostOrderFormRequest $request): Response
    {
        try {
            /** Проводим серию транзакций */
            DB::transaction(function () use ($request) {
                /** 
                 * В задании логика выбора склада 
                 * при создании заказа не описана. 
                 * Если значение warehouse_id не передано, 
                 * то берется первый из списка
                 */
                $warehouse_id = $request->safe()->warehouse_id == null ? Warehouse::first()->id : $request->safe()->warehouse_id;

                /**
                 * @var Order $order 
                 * 
                 * Создаем заказ
                 * */
                $order = Order::create([
                    'warehouse_id' => $warehouse_id,
                    'customer' => $request->customer,
                ]);

                foreach ($request->items as $product) {
                    /**
                     * Собираем товар в заказ со складов
                     */
                    Stock::subStocks($product['id'], $product['count'], $order->id);
                }

                foreach ($request->items as $product) {
                    /**
                     * Сохраняем товар из заказа в order_items
                     */
                    OrderItem::upsert(
                        [
                            'order_id' => $order->id,
                            'product_id' => $product['id'],
                            'count' => $product['count'],
                        ],
                        ['count' => DB::raw('count + ' . $product['count'])]
                    );
                }
            });
        } catch (\Exception $e) {
            return response($e->getMessage(), 400);
        }
        return response(status: 200);
    }
}
