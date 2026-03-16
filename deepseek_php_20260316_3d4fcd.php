<?php
/**
 * PUZIN API — официальный REST API маркетплейса Puzin (18+)
 * 
 * Базовый URL: https://yourdomain.com/api.php
 * Формат ответа: JSON
 * Поддерживаемые методы: GET, POST, PUT, DELETE
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Обработка preflight запросов CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// КОНФИГУРАЦИЯ И БАЗА ДАННЫХ (IN-MEMORY)
// ============================================

define('API_VERSION', '1.0.0');
define('SITE_NAME', 'Puzin');
define('AGE_RESTRICTION', 18);

// Имитация базы данных (в реальном проекте замените на MySQL/PostgreSQL)
$database = [
    'products' => [
        ['id' => 1, 'name' => 'VIVO X200 Pro', 'category' => 'vivo', 'price' => 89990, 'features' => ['6.78" AMOLED', 'Dimensity 9400', '16/512GB', 'IP68'], 'in_stock' => true, 'age_restricted' => false],
        ['id' => 2, 'name' => 'VIVO V40 SE', 'category' => 'vivo', 'price' => 24990, 'features' => ['6.67" AMOLED', 'Snapdragon 685', '8/256GB'], 'in_stock' => true, 'age_restricted' => false],
        ['id' => 3, 'name' => 'Наушники VIVO TWS 4', 'category' => 'vivo', 'price' => 4490, 'features' => ['Active NC', '30h battery', 'Bluetooth 5.3'], 'in_stock' => true, 'age_restricted' => false],
        ['id' => 4, 'name' => 'VIVO Watch 3', 'category' => 'vivo', 'price' => 11990, 'features' => ['AMOLED', 'GPS', 'Heart rate'], 'in_stock' => true, 'age_restricted' => false],
        ['id' => 5, 'name' => 'Кронштейн HARTENS', 'category' => 'tv', 'price' => 3290, 'features' => ['Надёжное крепление', 'до 65"', 'VESA'], 'in_stock' => true, 'age_restricted' => false],
        ['id' => 6, 'name' => 'Умная колонка', 'category' => 'audio', 'price' => 7990, 'features' => ['360° звук', 'голосовой помощник'], 'in_stock' => true, 'age_restricted' => false],
        ['id' => 7, 'name' => 'Экшн-камера', 'category' => 'photo', 'price' => 12990, 'features' => ['4K', 'Waterproof', 'Stabilization'], 'in_stock' => true, 'age_restricted' => false],
        ['id' => 8, 'name' => 'Средство для чистки (ХИМКАЛЬНИЦА)', 'category' => 'household', 'price' => 890, 'features' => ['Упаковка ленты', 'Закономерные формы', 'Времена года'], 'in_stock' => true, 'age_restricted' => false],
    ],
    'categories' => [
        ['id' => 1, 'name' => 'VIVO', 'slug' => 'vivo', 'product_count' => 4],
        ['id' => 2, 'name' => 'Электроника', 'slug' => 'electronics', 'product_count' => 7],
        ['id' => 3, 'name' => 'Кронштейны', 'slug' => 'tv-mounts', 'product_count' => 1],
        ['id' => 4, 'name' => 'Бытовая химия', 'slug' => 'household', 'product_count' => 1],
        ['id' => 5, 'name' => 'Аксессуары', 'slug' => 'accessories', 'product_count' => 3],
    ],
    'manager' => [
        'name' => 'Данил Задин',
        'position' => 'Персональный менеджер',
        'avatar' => '👨‍💼',
        'badge' => '18+ контент',
        'email' => 'danil.zadin@puzin.market',
        'working_hours' => '10:00 - 22:00 МСК',
        'age_restricted' => true
    ],
    'site_info' => [
        'name' => SITE_NAME,
        'api_version' => API_VERSION,
        'age_restriction' => AGE_RESTRICTION,
        'description' => 'Маркетплейс с широким каталогом и техникой VIVO'
    ]
];

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================

/**
 * Отправка JSON ответа
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Отправка ошибки
 */
function sendError($message, $statusCode = 400) {
    sendResponse(['error' => true, 'message' => $message], $statusCode);
}

/**
 * Получение параметров запроса (для GET)
 */
function getQueryParam($key, $default = null) {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Получение тела запроса (для POST, PUT)
 */
function getRequestBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Проверка age-restricted эндпоинтов (простая имитация)
 */
function checkAgeRestriction($required = true) {
    // В реальном API проверяли бы токен/возраст пользователя
    // Здесь просто заглушка — считаем что пользователь совершеннолетний
    return true;
}

// ============================================
// РОУТИНГ
// ============================================

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriParts = array_values(array_filter(explode('/', $requestUri)));

// Определяем эндпоинт (последняя часть URL или 'api')
$endpoint = $uriParts[array_key_last($uriParts)] ?? 'api';

// Парсинг ID из URL (если есть)
$id = null;
if (preg_match('/\/(products|categories|manager)\/(\d+)/', $requestUri, $matches)) {
    $id = (int)$matches[2];
} elseif (($key = array_search('products', $uriParts)) !== false && isset($uriParts[$key + 1]) && is_numeric($uriParts[$key + 1])) {
    $id = (int)$uriParts[$key + 1];
} elseif (($key = array_search('categories', $uriParts)) !== false && isset($uriParts[$key + 1]) && is_numeric($uriParts[$key + 1])) {
    $id = (int)$uriParts[$key + 1];
}

// ============================================
// ОБРАБОТКА ЭНДПОИНТОВ
// ============================================

// Корневой эндпоинт /api или /api.php
if ($endpoint === 'api' || $endpoint === 'api.php') {
    sendResponse([
        'site' => $database['site_info'],
        'manager' => $database['manager'],
        'endpoints' => [
            '/api' => 'Этот список эндпоинтов',
            '/products' => 'Список товаров (GET) / Добавить товар (POST)',
            '/products/{id}' => 'Получить/обновить/удалить товар',
            '/categories' => 'Список категорий',
            '/manager' => 'Информация о менеджере Даниле Задине',
            '/age-check' => 'Проверка возрастных ограничений'
        ],
        'age_restriction' => AGE_RESTRICTION . '+'
    ]);
}

// ============================================
// PRODUCTS ENDPOINTS
// ============================================

elseif (strpos($requestUri, '/products') !== false) {
    
    // GET /products — список всех товаров
    if ($requestMethod === 'GET' && !$id) {
        $category = getQueryParam('category');
        $minPrice = getQueryParam('min_price');
        $maxPrice = getQueryParam('max_price');
        $inStock = getQueryParam('in_stock');
        $search = getQueryParam('search');
        
        $products = $database['products'];
        
        // Фильтрация по категории
        if ($category) {
            $products = array_filter($products, function($p) use ($category) {
                return stripos($p['category'], $category) !== false;
            });
        }
        
        // Фильтрация по цене
        if ($minPrice !== null) {
            $products = array_filter($products, function($p) use ($minPrice) {
                return $p['price'] >= (int)$minPrice;
            });
        }
        if ($maxPrice !== null) {
            $products = array_filter($products, function($p) use ($maxPrice) {
                return $p['price'] <= (int)$maxPrice;
            });
        }
        
        // Фильтр наличия
        if ($inStock !== null && $inStock === 'true') {
            $products = array_filter($products, function($p) {
                return $p['in_stock'] === true;
            });
        }
        
        // Поиск по названию
        if ($search) {
            $products = array_filter($products, function($p) use ($search) {
                return stripos($p['name'], $search) !== false;
            });
        }
        
        sendResponse(['products' => array_values($products)]);
    }
    
    // GET /products/{id} — конкретный товар
    elseif ($requestMethod === 'GET' && $id) {
        $product = null;
        foreach ($database['products'] as $p) {
            if ($p['id'] === $id) {
                $product = $p;
                break;
            }
        }
        
        if ($product) {
            sendResponse(['product' => $product]);
        } else {
            sendError('Товар не найден', 404);
        }
    }
    
    // POST /products — создать новый товар
    elseif ($requestMethod === 'POST' && !$id) {
        // Проверка age restriction (для некоторых товаров может требоваться 18+)
        checkAgeRestriction(false); // не требуем, но можно добавить логику
        
        $data = getRequestBody();
        
        // Валидация
        if (!$data || !isset($data['name']) || !isset($data['price'])) {
            sendError('Не указаны обязательные поля: name, price', 400);
        }
        
        // Создаём новый ID
        $newId = max(array_column($database['products'], 'id')) + 1;
        
        $newProduct = [
            'id' => $newId,
            'name' => $data['name'],
            'category' => $data['category'] ?? 'other',
            'price' => (int)$data['price'],
            'features' => $data['features'] ?? [],
            'in_stock' => $data['in_stock'] ?? true,
            'age_restricted' => $data['age_restricted'] ?? false
        ];
        
        // В реальном приложении здесь добавление в БД
        // $database['products'][] = $newProduct;
        
        sendResponse(['message' => 'Товар создан', 'product' => $newProduct], 201);
    }
    
    // PUT /products/{id} — обновить товар
    elseif ($requestMethod === 'PUT' && $id) {
        $data = getRequestBody();
        
        // Поиск товара
        $productIndex = -1;
        foreach ($database['products'] as $index => $p) {
            if ($p['id'] === $id) {
                $productIndex = $index;
                break;
            }
        }
        
        if ($productIndex === -1) {
            sendError('Товар не найден', 404);
        }
        
        // Обновление (в реальности — апдейт в БД)
        $updatedProduct = array_merge($database['products'][$productIndex], $data);
        
        sendResponse(['message' => 'Товар обновлён', 'product' => $updatedProduct]);
    }
    
    // DELETE /products/{id} — удалить товар
    elseif ($requestMethod === 'DELETE' && $id) {
        // Поиск товара
        $exists = false;
        foreach ($database['products'] as $p) {
            if ($p['id'] === $id) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            sendError('Товар не найден', 404);
        }
        
        // В реальности — удаление из БД
        sendResponse(['message' => 'Товар удалён', 'deleted_id' => $id]);
    }
    
    else {
        sendError('Метод не поддерживается для этого эндпоинта', 405);
    }
}

// ============================================
// CATEGORIES ENDPOINTS
// ============================================

elseif (strpos($requestUri, '/categories') !== false) {
    
    // GET /categories
    if ($requestMethod === 'GET' && !$id) {
        sendResponse(['categories' => $database['categories']]);
    }
    
    // GET /categories/{id}
    elseif ($requestMethod === 'GET' && $id) {
        $category = null;
        foreach ($database['categories'] as $c) {
            if ($c['id'] === $id) {
                $category = $c;
                break;
            }
        }
        
        if ($category) {
            sendResponse(['category' => $category]);
        } else {
            sendError('Категория не найдена', 404);
        }
    }
    
    else {
        sendError('Метод не поддерживается для категорий', 405);
    }
}

// ============================================
// MANAGER ENDPOINT
// ============================================

elseif (strpos($requestUri, '/manager') !== false) {
    
    if ($requestMethod === 'GET') {
        // Проверка 18+ для менеджера (так как он помечен 18+)
        checkAgeRestriction(true);
        
        sendResponse(['manager' => $database['manager']]);
    }
    else {
        sendError('Метод не поддерживается', 405);
    }
}

// ============================================
// AGE CHECK ENDPOINT
// ============================================

elseif (strpos($requestUri, '/age-check') !== false) {
    
    if ($requestMethod === 'GET') {
        $userAge = getQueryParam('age');
        
        if ($userAge && (int)$userAge >= AGE_RESTRICTION) {
            sendResponse([
                'access_granted' => true,
                'message' => 'Доступ разрешён',
                'age_restriction' => AGE_RESTRICTION
            ]);
        } elseif ($userAge) {
            sendResponse([
                'access_granted' => false,
                'message' => 'Доступ запрещён. Вам нет 18 лет',
                'age_restriction' => AGE_RESTRICTION
            ], 403);
        } else {
            sendResponse([
                'message' => 'Укажите параметр age для проверки',
                'example' => '/age-check?age=20'
            ]);
        }
    }
    else {
        sendError('Метод не поддерживается', 405);
    }
}

// ============================================
// 404 — ЭНДПОИНТ НЕ НАЙДЕН
// ============================================

else {
    sendError('API эндпоинт не найден', 404);
}
?>