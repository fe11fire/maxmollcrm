#Maxmollcrm

[Microcrm для торговли](https://docs.google.com/document/d/1WSVo8by4D13JUJKpB8m58itoHD_9YVRZXr2zr8sWa_U/edit?usp=drivesdk)

## Оглавление
- [Оглавление](#оглавление)
- [Установка и настройка](#установка-и-настройка)
- [REST API](#rest-api)


## Установка и настройка
Клонируем образ:

```
git clone git@github.com:fe11fire/maxmollcrm.git .
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

<span style="color:red">*Команда может вернуть ошибку. Обычно срабатывает со второго раза*</span>
```
docker-compose run --rm artisan migrate:fresh --seed
```

## REST API

Примеры команд представлены в каталоге [requests](https://github.com/fe11fire/maxmollcrm/tree/main/requests)
