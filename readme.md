Клонируем образ:

```
<!-- git clone https://github.com/fe11fire/_shop.git . -->
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

Тестовое задание:
```
http://localhost:8000/
```