# Maxmollcrm

[Microcrm для торговли](https://docs.google.com/document/d/1WSVo8by4D13JUJKpB8m58itoHD_9YVRZXr2zr8sWa_U/edit?usp=drivesdk)

## Оглавление
- [Оглавление](#оглавление)
- [Установка и настройка](#установка-и-настройка)
- [REST API](#rest-api)
  - [Список складов](#список-складов)
  - [Список товаров с их остатками по складам](#список-товаров-с-их-остатками-по-складам)
  - [Список заказов (с фильтрами и настраиваемой пагинацией)](#список-заказов-с-фильтрами-и-настраиваемой-пагинацией)
  - [Создание заказа (в заказе может быть несколько позиций с разным количеством)](#создание-заказа-в-заказе-может-быть-несколько-позиций-с-разным-количеством)
  - [Обновление заказа (данные покупателя и список позиций, но не статус)](#обновление-заказа-данные-покупателя-и-список-позиций-но-не-статус)
  - [Завершение заказ](#завершение-заказ)
  - [Отмена заказа](#отмена-заказа)
  - [Возобновление заказа](#возобновление-заказа)
  - [История изменений остатков товаров](#история-изменений-остатков-товаров)


## Установка и настройка
Клонируем образ:

```
git clone https://github.com/fe11fire/maxmollcrm.git .
```

Устанавливаем зависимости:
```
docker-compose run --rm composer install
```

Запускаем окружение:
```
docker-compose up nginx --build -d
```

Добавляем тестовые данные:

```
docker-compose run --rm artisan migrate:fresh --seed
```

## REST API

### Список складов

```
GET {{host}}/warehouses
```

### Список товаров с их остатками по складам

```
GET {{host}}/stocks
```

### Список заказов (с фильтрами и настраиваемой пагинацией)

```
GET {{host}}/orders
```
### Создание заказа (в заказе может быть несколько позиций с разным количеством)

```
POST {{host}}/order

{
    "customer": string,
    "items" : array of objects {"id": int, "count": int},
    "warehouse_id": int | null
}
```

### Обновление заказа (данные покупателя и список позиций, но не статус)

```
PUT {{host}}/order

{
    "id": int,
    "customer": string | null,
    "items" : array of objects {"id": int, "count": int} | null,
}
```

### Завершение заказ

```
PUT {{host}}/order/complete

{
    "id": int
}

        OR

GET {{host}}/order/{{id}}/cancel
```

### Отмена заказа

```
PUT {{host}}/order/cancel

{
    "id": int
}

        OR

GET {{host}}/order/{{id}}/cancel
```

### Возобновление заказа

```
PUT {{host}}/order/resume

{
    "id": int
}

        OR

GET {{host}}/order/{{id}}/resume
```

### История изменений остатков товаров

```
GET {{host}}/stocks/history

{
    "product_id": int | null,
    "warehouse_id": int | null,
    "period_start": string:format(Y-m-d,Y-m-d H:i:s,Y-m,Y) | null,
    "period_end": string:format(Y-m-d,Y-m-d H:i:s,Y-m,Y) | null
}
```


Примеры команд представлены в каталоге [requests](https://github.com/fe11fire/maxmollcrm/tree/main/requests)
