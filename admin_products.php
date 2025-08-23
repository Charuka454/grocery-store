<?php
@include 'config.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if(!$admin_id){
   header('location:login.php');
   exit;
}

$message = [];

/* ---------- helpers for datetime-local <-> MySQL DATETIME ---------- */
function to_mysql_dt(?string $local){
   // Accepts: "YYYY-MM-DDTHH:MM" or already "YYYY-MM-DD HH:MM:SS"
   if(!$local){ return null; }
   $local = trim($local);
   if($local === '') return null;

   // If it contains 'T', it's from datetime-local (seconds often omitted)
   if (strpos($local, 'T') !== false) {
      // Normalize to seconds
      // e.g. 2025-08-22T21:35  ->  2025-08-22 21:35:00
      $local = str_replace('T', ' ', $local);
      if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $local)) {
         $local .= ':00';
      }
   }
   // Very light validation
   return $local;
}
function to_input_dt(?string $mysql){
   // Convert "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM" for datetime-local value
   if(!$mysql){ return ''; }
   $ts = strtotime($mysql);
   if($ts === false){ return ''; }
   return date('Y-m-d\TH:i', $ts);
}

/* =========================================
   ADD PRODUCT  (with categories + quantity)
========================================= */
if(isset($_POST['add_product'])){
   $name        = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_STRING);
   $price       = isset($_POST['price']) ? (float)$_POST['price'] : 0;
   $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
   $details     = filter_var($_POST['details'] ?? '', FILTER_SANITIZE_STRING);
   $quantity    = isset($_POST['quantity']) ? max(0, (int)$_POST['quantity']) : 0;

   $image          = filter_var($_FILES['image']['name'] ?? '', FILTER_SANITIZE_STRING);
   $image_size     = $_FILES['image']['size'] ?? 0;
   $image_tmp_name = $_FILES['image']['tmp_name'] ?? '';
   $image_folder   = $image ? ('uploaded_img/'.$image) : '';

   // Basic validation
   if($category_id <= 0){
      $message[] = 'Please choose a category.';
   }
   if($name === ''){
      $message[] = 'Product name is required.';
   }
   if($price < 0){
      $message[] = 'Price must be >= 0.';
   }
   if($quantity < 0){
      $message[] = 'Quantity must be >= 0.';
   }

   // Unique product name check
   $select_products = $conn->prepare("SELECT 1 FROM `products` WHERE name = ? LIMIT 1");
   $select_products->execute([$name]);

   if($select_products->rowCount() > 0){
      $message[] = 'Product name already exists!';
   }elseif(empty($message)){
      // NOTE: requires the new `quantity` column in DB
      $insert_products = $conn->prepare("
         INSERT INTO `products`(name, category_id, details, price, quantity, image)
         VALUES(?,?,?,?,?,?)
      ");
      $insert_products->execute([$name, $category_id, $details, $price, $quantity, $image]);

      if($insert_products){
         if($image && $image_size > 2000000){
            $message[] = 'Image size is too large!';
         }elseif($image){
            @move_uploaded_file($image_tmp_name, $image_folder);
            $message[] = 'New product added!';
         }else{
            $message[] = 'New product added (no image).';
         }
      }
   }
}

/* =========================================
   DELETE PRODUCT + cascade deletes
========================================= */
if(isset($_GET['delete'])){
   $delete_id = (int)$_GET['delete'];

   // delete image
   $select_delete_image = $conn->prepare("SELECT image FROM `products` WHERE id = ?");
   $select_delete_image->execute([$delete_id]);
   if($select_delete_image->rowCount()){
      $fetch_delete_image = $select_delete_image->fetch(PDO::FETCH_ASSOC);
      if(!empty($fetch_delete_image['image'])){
         @unlink('uploaded_img/'.$fetch_delete_image['image']);
      }
   }

   // delete product
   $delete_products = $conn->prepare("DELETE FROM `products` WHERE id = ?");
   $delete_products->execute([$delete_id]);

   // cascade deletes
   $delete_wishlist = $conn->prepare("DELETE FROM `wishlist` WHERE pid = ?");
   $delete_wishlist->execute([$delete_id]);

   $delete_cart = $conn->prepare("DELETE FROM `cart` WHERE pid = ?");
   $delete_cart->execute([$delete_id]);

   // remove promotions tied to this product
   $delete_promos = $conn->prepare("DELETE FROM `promotions` WHERE product_id = ?");
   $delete_promos->execute([$delete_id]);

   header('location:admin_products.php');
   exit;
}

/* =========================================
   PROMOTION: Create/Update (CRUD)
========================================= */
if(isset($_POST['save_promo'])){
   $promo_id         = isset($_POST['promo_id']) ? (int)$_POST['promo_id'] : 0;
   $product_id       = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
   $promo_price      = (isset($_POST['promo_price']) && $_POST['promo_price'] !== '') ? (float)$_POST['promo_price'] : null;
   $discount_percent = (isset($_POST['discount_percent']) && $_POST['discount_percent'] !== '') ? (float)$_POST['discount_percent'] : null;

   $label     = isset($_POST['label']) ? trim(filter_var($_POST['label'], FILTER_SANITIZE_STRING)) : 'Limited Offer';
   // from datetime-local inputs
   $starts_at = to_mysql_dt($_POST['starts_at'] ?? null);
   $ends_at   = to_mysql_dt($_POST['ends_at'] ?? null);
   $active    = isset($_POST['active']) ? 1 : 0;

   $errs = [];
   if($product_id <= 0){ $errs[] = 'Choose a valid product.'; }
   if($promo_price === null && $discount_percent === null){ $errs[] = 'Set either Promo Price or Discount %.'; }
   if($promo_price !== null && $promo_price < 0){ $errs[] = 'Promo price must be >= 0.'; }
   if($discount_percent !== null && ($discount_percent < 0 || $discount_percent > 95)){ $errs[] = 'Discount % must be between 0 and 95.'; }

   if(empty($errs)){
      if($promo_id > 0){
         $sql = "UPDATE promotions
                 SET product_id=?, promo_price=?, discount_percent=?, label=?, starts_at=?, ends_at=?, active=?
                 WHERE id=?";
         $stmt = $conn->prepare($sql);
         $stmt->execute([$product_id, $promo_price, $discount_percent, $label, $starts_at, $ends_at, $active, $promo_id]);
         $message[] = 'Promotion updated.';
      }else{
         // enforce one-per-product (latest wins)
         $chk = $conn->prepare("SELECT id FROM promotions WHERE product_id=? ORDER BY id DESC LIMIT 1");
         $chk->execute([$product_id]);
         if($chk->rowCount()){
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            $sql = "UPDATE promotions
                    SET promo_price=?, discount_percent=?, label=?, starts_at=?, ends_at=?, active=?
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$promo_price, $discount_percent, $label, $starts_at, $ends_at, $active, (int)$row['id']]);
            $message[] = 'Promotion updated.';
         }else{
            $sql = "INSERT INTO promotions (product_id, promo_price, discount_percent, label, starts_at, ends_at, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$product_id, $promo_price, $discount_percent, $label, $starts_at, $ends_at, $active]);
            $message[] = 'Promotion created.';
         }
      }
   }else{
      $message[] = implode(' ', $errs);
   }

   header('location:admin_products.php#p'.$product_id);
   exit;
}

/* =========================================
   PROMOTION: Delete (CRUD)
========================================= */
if(isset($_GET['delete_promo'])){
   $del_pid     = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
   $del_promoid = (int)$_GET['delete_promo'];
   $stmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
   $stmt->execute([$del_promoid]);
   $message[] = 'Promotion deleted.';
   header('location:admin_products.php#p'.$del_pid);
   exit;
}

/* =========================================
   Load Categories for dropdowns/filters
========================================= */
$cats_stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$cats_stmt->execute();
$all_categories = $cats_stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   Search / Filter Products
========================================= */
$q   = isset($_GET['q'])   ? trim($_GET['q']) : '';
$cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

$p_where = [];
$p_params = [];

if($q !== ''){
   $like = "%{$q}%";
   $p_where[] = "(p.name LIKE ? OR p.details LIKE ? OR c.name LIKE ?)";
   array_push($p_params, $like, $like, $like);
}
if($cat > 0){
   $p_where[] = "p.category_id = ?";
   $p_params[] = $cat;
}
$where_sql = $p_where ? ('WHERE '.implode(' AND ', $p_where)) : '';

/* Count for label */
$stmt_count = $conn->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id $where_sql");
$stmt_count->execute($p_params);
$total_results = (int)$stmt_count->fetchColumn();

/* =========================================
   Fetch Products (joined with category)
========================================= */
$show_products = $conn->prepare("
  SELECT p.*, c.name AS category_name
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
  $where_sql
  ORDER BY p.id DESC
");
$show_products->execute($p_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8" />
   <meta http-equiv="X-UA-Compatible" content="IE=edge" />
   <meta name="viewport" content="width=device-width, initial-scale=1.0" />
   <title>Admin • Products</title>

   <!-- Tailwind CSS -->
   <script src="https://cdn.tailwindcss.com"></script>

   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" />
   <style>
     .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
     .line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
   </style>
</head>
<body class="bg-gray-50">
<?php include 'admin_header.php'; ?>

<!-- Main wrapper to match your dashboard offset -->
<div class="ml-64 pt-16 min-h-screen">
  <div class="p-6">

    <!-- Gradient Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-white mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Products</h1>
          <p class="text-blue-100"><?= date('l, F j, Y'); ?></p>
        </div>
        <a href="admin_page.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded transition">
          <i class="fas fa-home"></i> Dashboard
        </a>
      </div>
    </div>

    <!-- Messages -->
    <?php if(!empty($message)): ?>
      <div class="mb-4 space-y-2">
        <?php foreach($message as $msg): ?>
          <div class="flex items-center gap-2 bg-blue-50 text-blue-800 border border-blue-200 rounded px-4 py-2">
            <i class="fas fa-info-circle"></i>
            <span><?= htmlspecialchars($msg); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="#add-form" class="bg-blue-600 hover:bg-blue-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-plus mb-2 block"></i>
          <span class="text-sm">Add Product</span>
        </a>
        <a href="admin_orders.php" class="bg-green-600 hover:bg-green-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-list mb-2 block"></i>
          <span class="text-sm">View Orders</span>
        </a>
        <a href="admin_users.php" class="bg-purple-600 hover:bg-purple-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-users mb-2 block"></i>
          <span class="text-sm">Manage Users</span>
        </a>
        <a href="admin_contacts.php" class="bg-orange-600 hover:bg-orange-700 text-white p-3 rounded text-center transition-colors">
          <i class="fas fa-envelope mb-2 block"></i>
          <span class="text-sm">Messages</span>
        </a>
      </div>
    </div>

    <!-- Add Product Form -->
    <div id="add-form" class="bg-white rounded-lg shadow p-6 mb-8">
      <h3 class="text-lg font-semibold mb-4">Add New Product</h3>

      <?php if(empty($all_categories)): ?>
        <div class="mb-4 p-3 rounded bg-yellow-50 text-yellow-900 border border-yellow-200">
          No categories found. <a class="underline text-blue-700" href="admin_categories.php">Create a category</a> first.
        </div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-4">
            <input type="text" name="name" required placeholder="Enter product name"
                   class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />

            <select name="category_id" required
                    class="w-full border rounded px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="" disabled selected>Select category</option>
              <?php foreach($all_categories as $cat): ?>
                <option value="<?= (int)$cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="space-y-4">
            <input type="number" step="0.01" min="0" inputmode="decimal" name="price" required placeholder="Enter product price (Rs)"
                   class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />

            <input type="file" name="image" accept="image/jpg, image/jpeg, image/png"
                   class="w-full border rounded px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <!-- NEW: Quantity -->
        <div>
          <label class="block text-sm text-gray-600 mb-1">Quantity in stock</label>
          <input type="number" name="quantity" min="0" step="1" required placeholder="e.g. 10"
                 class="w-full md:w-56 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <textarea name="details" required placeholder="Enter product details" rows="5"
                  class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>

        <button type="submit" name="add_product"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded transition">
          <i class="fas fa-save"></i> Add Product
        </button>
      </form>
    </div>

    <!-- Toolbar: Search / Filter -->
    <div class="bg-white rounded-lg shadow p-4 md:p-6 mb-4">
      <form method="GET" class="flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
        <div>
          <h3 class="text-lg font-semibold">Products Added</h3>
          <?php if($q !== '' || $cat > 0): ?>
            <p class="text-gray-500 text-sm">
              Showing <span class="font-medium"><?= $total_results; ?></span> result(s)
              <?php if($q !== ''): ?> for “<span class="font-medium"><?= htmlspecialchars($q); ?></span>”<?php endif; ?>
              <?php if($cat > 0): ?>
                in <span class="font-medium">
                  <?php
                    $nm = array_values(array_filter($all_categories, fn($c)=> (int)$c['id']===$cat));
                    echo $nm ? htmlspecialchars($nm[0]['name']) : 'category';
                  ?>
                </span>
              <?php endif; ?>
              <a href="admin_products.php" class="text-blue-600 hover:underline ml-2">Reset</a>
            </p>
          <?php else: ?>
            <p class="text-gray-500 text-sm">Search, filter and manage products</p>
          <?php endif; ?>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
          <div class="relative">
            <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input
              type="text"
              name="q"
              value="<?= htmlspecialchars($q); ?>"
              placeholder="Search product, details, category…"
              class="w-full sm:w-80 pl-10 pr-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <select name="cat" class="w-full sm:w-52 py-2 px-3 border rounded bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="0">All categories</option>
            <?php foreach($all_categories as $catOpt): ?>
              <option value="<?= (int)$catOpt['id']; ?>" <?= $cat===(int)$catOpt['id']?'selected':''; ?>>
                <?= htmlspecialchars($catOpt['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition">
            <i class="fas fa-filter"></i> Apply
          </button>
        </div>
      </form>
    </div>

    <!-- Products Grid -->
    <div class="bg-white rounded-lg shadow">
      <div class="p-4 md:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          <?php
            if($show_products->rowCount() > 0){
              while($fetch_products = $show_products->fetch(PDO::FETCH_ASSOC)){
                $pid = (int)$fetch_products['id'];

                // Load latest promo (if any)
                $promo = null;
                $getPromo = $conn->prepare("SELECT * FROM promotions WHERE product_id = ? ORDER BY id DESC LIMIT 1");
                $getPromo->execute([$pid]);
                if($getPromo->rowCount()){
                  $promo = $getPromo->fetch(PDO::FETCH_ASSOC);
                }

                // Final price preview (if live)
                $now   = date('Y-m-d H:i:s');
                $base  = (float)$fetch_products['price'];
                $final = null; $isLive = false;

                if($promo){
                  $inWindow = (empty($promo['starts_at']) || $promo['starts_at'] <= $now)
                           && (empty($promo['ends_at'])   || $promo['ends_at']   >= $now);
                  if((int)$promo['active'] === 1 && $inWindow){
                    if($promo['promo_price'] !== null && $promo['promo_price'] !== ''){
                      $final = (float)$promo['promo_price'];
                    }elseif($promo['discount_percent'] !== null && $promo['discount_percent'] !== ''){
                      $final = max(0, $base * (1 - ((float)$promo['discount_percent']/100)));
                    }
                    if($final !== null && $final < $base){ $isLive = true; }
                  }
                }

                $qty = (int)($fetch_products['quantity'] ?? 0);
                $qtyState = 'out';
                if ($qty > 10)      $qtyState = 'in';
                else if ($qty > 0)  $qtyState = 'low';
          ?>
          <span id="p<?= $pid; ?>" class="relative -top-20 block"></span>

          <div class="rounded-lg border hover:shadow-md transition bg-white overflow-hidden">
            <!-- Image -->
            <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden relative">
              <?php if(!empty($fetch_products['image'])): ?>
                <img src="uploaded_img/<?= htmlspecialchars($fetch_products['image']); ?>"
                     alt=""
                     class="w-full h-full object-cover" />
              <?php else: ?>
                <i class="fas fa-image text-gray-400 text-4xl"></i>
              <?php endif; ?>

              <!-- Stock badge (top-left) -->
              <div class="absolute top-2 left-2">
                <?php if($qtyState==='in'): ?>
                  <span class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-800">In stock: <?= $qty; ?></span>
                <?php elseif($qtyState==='low'): ?>
                  <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-800">Low: <?= $qty; ?></span>
                <?php else: ?>
                  <span class="text-xs px-2 py-1 rounded bg-rose-100 text-rose-800">Out of stock</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Content -->
            <div class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <h4 class="font-semibold line-clamp-2"><?= htmlspecialchars($fetch_products['name']); ?></h4>
                <span class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700">
                  <?= htmlspecialchars($fetch_products['category_name'] ?? 'Uncategorized'); ?>
                </span>
              </div>

              <div class="text-sm text-gray-600 line-clamp-3" title="<?= htmlspecialchars($fetch_products['details']); ?>">
                <?= htmlspecialchars($fetch_products['details']); ?>
              </div>

              <div class="flex items-center gap-2 pt-1">
                <?php if($isLive): ?>
                  <span class="text-sm text-gray-500 line-through">Rs <?= number_format($base,2); ?></span>
                  <span class="text-green-700 font-semibold">Rs <?= number_format($final,2); ?></span>
                  <?php if($final > 0 && $base > 0): ?>
                    <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">
                      SAVE <?= round((($base-$final)/$base)*100); ?>%
                    </span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-gray-800 font-semibold">Rs <?= number_format((float)$fetch_products['price'],2); ?></span>
                <?php endif; ?>
              </div>

              <?php if($isLive): ?>
                <div class="text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-800 inline-flex items-center gap-1">
                  <i class="fas fa-bolt"></i> LIVE PROMO
                </div>
              <?php elseif($promo): ?>
                <div class="text-xs px-2 py-1 rounded bg-yellow-100 text-yellow-800 inline-flex items-center gap-1">
                  <i class="fas fa-circle-info"></i> Promo exists (not live)
                </div>
              <?php else: ?>
                <div class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 inline-flex items-center gap-1">
                  <i class="fas fa-tag"></i> No promotion
                </div>
              <?php endif; ?>

              <!-- Actions -->
              <div class="flex items-center gap-2 pt-2">
                <a href="admin_update_product.php?update=<?= $pid; ?>"
                   class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-2 rounded transition">
                  <i class="fas fa-pen"></i> update
                </a>
                <a href="admin_products.php?delete=<?= $pid; ?>"
                   onclick="return confirm('delete this product?');"
                   class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-2 rounded transition">
                  <i class="fas fa-trash"></i> delete
                </a>
              </div>
            </div>

            <!-- Promo editor -->
            <div class="p-4 bg-gray-50 border-t">
              <form method="POST" action="" class="space-y-3">
                <input type="hidden" name="product_id" value="<?= $pid; ?>">
                <input type="hidden" name="promo_id" value="<?= $promo ? (int)$promo['id'] : 0; ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Promo Price (optional)</label>
                    <input type="number" step="0.01" name="promo_price"
                           value="<?= $promo && $promo['promo_price'] !== null ? htmlspecialchars($promo['promo_price']) : ''; ?>"
                           placeholder="e.g. 2499.00"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Discount % (optional)</label>
                    <input type="number" step="0.01" name="discount_percent"
                           value="<?= $promo && $promo['discount_percent'] !== null ? htmlspecialchars($promo['discount_percent']) : ''; ?>"
                           placeholder="e.g. 20"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Label</label>
                    <input type="text" name="label"
                           value="<?= $promo ? htmlspecialchars($promo['label']) : 'Limited Offer'; ?>"
                           maxlength="60"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                  <div class="flex items-center gap-2 pt-6 sm:pt-6">
                    <input type="checkbox" id="active_<?= $pid; ?>" name="active"
                           class="w-4 h-4"
                           <?= $promo ? ((int)$promo['active']===1 ? 'checked' : '') : 'checked'; ?> />
                    <label for="active_<?= $pid; ?>" class="text-sm text-gray-700">Active</label>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Starts At</label>
                    <!-- NEW: datetime-local input -->
                    <input type="datetime-local" name="starts_at"
                           value="<?= $promo ? htmlspecialchars(to_input_dt($promo['starts_at'] ?? null)) : ''; ?>"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Ends At</label>
                    <!-- NEW: datetime-local input -->
                    <input type="datetime-local" name="ends_at"
                           value="<?= $promo ? htmlspecialchars(to_input_dt($promo['ends_at'] ?? null)) : ''; ?>"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                </div>

                <div class="flex items-center flex-wrap gap-2">
                  <button type="submit" name="save_promo"
                          class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-3 py-2 rounded transition">
                    <i class="fas fa-badge-percent"></i> Save Promotion
                  </button>

                  <?php if($promo): ?>
                    <a href="admin_products.php?delete_promo=<?= (int)$promo['id']; ?>&pid=<?= $pid; ?>"
                       onclick="return confirm('Delete this promotion?');"
                       class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-2 rounded transition">
                      <i class="fas fa-trash"></i> Delete Promotion
                    </a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
          <?php
              }
            }else{
              echo '<div class="col-span-full text-center text-gray-500 py-8">No products found.</div>';
            }
          ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="js/script.js"></script>
</body>
</html>
