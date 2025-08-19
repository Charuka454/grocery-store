<?php
@include 'config.php';
session_start();

$admin_id = $_SESSION['admin_id'];
if(!isset($admin_id)){
   header('location:login.php');
   exit;
}

/* =========================================
   ADD PRODUCT (original)
========================================= */
if(isset($_POST['add_product'])){
   $name     = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
   $price    = filter_var($_POST['price'], FILTER_SANITIZE_STRING);
   $category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
   $details  = filter_var($_POST['details'], FILTER_SANITIZE_STRING);

   $image          = filter_var($_FILES['image']['name'], FILTER_SANITIZE_STRING);
   $image_size     = $_FILES['image']['size'];
   $image_tmp_name = $_FILES['image']['tmp_name'];
   $image_folder   = 'uploaded_img/'.$image;

   $select_products = $conn->prepare("SELECT * FROM `products` WHERE name = ?");
   $select_products->execute([$name]);

   if($select_products->rowCount() > 0){
      $message[] = 'product name already exist!';
   }else{
      $insert_products = $conn->prepare("INSERT INTO `products`(name, category, details, price, image) VALUES(?,?,?,?,?)");
      $insert_products->execute([$name, $category, $details, $price, $image]);

      if($insert_products){
         if($image_size > 2000000){
            $message[] = 'image size is too large!';
         }else{
            move_uploaded_file($image_tmp_name, $image_folder);
            $message[] = 'new product added!';
         }
      }
   }
}

/* =========================================
   DELETE PRODUCT (original) + delete promos
========================================= */
if(isset($_GET['delete'])){
   $delete_id = (int)$_GET['delete'];

   // delete image
   $select_delete_image = $conn->prepare("SELECT image FROM `products` WHERE id = ?");
   $select_delete_image->execute([$delete_id]);
   if($select_delete_image->rowCount()){
      $fetch_delete_image = $select_delete_image->fetch(PDO::FETCH_ASSOC);
      @unlink('uploaded_img/'.$fetch_delete_image['image']);
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
   $starts_at = isset($_POST['starts_at']) ? trim($_POST['starts_at']) : '';
   $ends_at   = isset($_POST['ends_at'])   ? trim($_POST['ends_at'])   : '';
   $active    = isset($_POST['active']) ? 1 : 0;

   $starts_at = ($starts_at === '') ? null : $starts_at;
   $ends_at   = ($ends_at === '')   ? null : $ends_at;

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
   SEARCH helper + state
========================================= */
function build_products_search_clause(string $term, array &$params): string {
    $term = trim($term);
    if ($term === '') return '';
    $like = "%{$term}%";
    $params[] = $like; // name
    $params[] = $like; // category
    $params[] = $like; // details
    return " WHERE (name LIKE ? OR category LIKE ? OR details LIKE ?) ";
}
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$params = [];
$where  = build_products_search_clause($q, $params);

// Count results (for UX)
$sql_count = "SELECT COUNT(*) AS c FROM `products`" . $where;
$stmt_count = $conn->prepare($sql_count);
$stmt_count->execute($params);
$total_results = (int)($stmt_count->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
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

    <!-- Quick Actions (match dashboard) -->
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

      <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-4">
            <input type="text" name="name" required placeholder="Enter product name"
                   class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />

            <select name="category" required
                    class="w-full border rounded px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="" disabled selected>Select category</option>
              <option value="Wood">Wood</option>
              <option value="Clothes">Clothes</option>
              <option value="Wall">Wall decorations</option>
              <option value="Brass">Brass</option>
            </select>
          </div>

          <div class="space-y-4">
            <input type="number" step="0.01" min="0" name="price" required placeholder="Enter product price"
                   class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />

            <input type="file" name="image" required accept="image/jpg, image/jpeg, image/png"
                   class="w-full border rounded px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <textarea name="details" required placeholder="Enter product details" rows="5"
                  class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>

        <button type="submit" name="add_product"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded transition">
          <i class="fas fa-save"></i> Add Product
        </button>
      </form>
    </div>

    <!-- Products Grid + SEARCH -->
    <div class="bg-white rounded-lg shadow">
      <div class="p-4 md:p-6 border-b">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold">Products Added</h3>
            <?php if($q !== ''): ?>
              <p class="text-gray-500 text-sm">
                Showing <span class="font-medium"><?= $total_results; ?></span> result(s) for
                “<span class="font-medium"><?= htmlspecialchars($q); ?></span>”.
                <a href="admin_products.php" class="text-blue-600 hover:underline ml-2">Reset</a>
              </p>
            <?php else: ?>
              <p class="text-gray-500 text-sm">Manage products and promotions</p>
            <?php endif; ?>
          </div>

          <!-- Search form -->
          <form method="GET" class="w-full md:w-auto">
            <div class="relative">
              <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($q); ?>"
                placeholder="Search name, category, details…"
                class="w-full md:w-80 pl-10 pr-10 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <?php if($q !== ''): ?>
                <a href="admin_products.php" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" title="Clear">
                  <i class="fas fa-xmark"></i>
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <div class="p-4 md:p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          <?php
            // MAIN PRODUCT QUERY with optional WHERE
            $sql = "SELECT * FROM `products`" . $where . " ORDER BY id DESC";
            $show_products = $conn->prepare($sql);
            $show_products->execute($params);

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
                  $inWindow = ($promo['starts_at'] === null || $promo['starts_at'] <= $now)
                           && ($promo['ends_at'] === null   || $promo['ends_at']   >= $now);
                  if((int)$promo['active'] === 1 && $inWindow){
                    if($promo['promo_price'] !== null && $promo['promo_price'] !== ''){
                      $final = (float)$promo['promo_price'];
                    }elseif($promo['discount_percent'] !== null && $promo['discount_percent'] !== ''){
                      $final = max(0, $base * (1 - ((float)$promo['discount_percent']/100)));
                    }
                    if($final !== null && $final < $base){ $isLive = true; }
                  }
                }
          ?>
          <span id="p<?= $pid; ?>" class="relative -top-20 block"></span>

          <div class="rounded-lg border hover:shadow-md transition bg-white overflow-hidden">
            <!-- Image -->
            <div class="aspect-[4/3] bg-gray-100 flex items-center justify-center overflow-hidden">
              <?php if(!empty($fetch_products['image'])): ?>
                <img src="uploaded_img/<?= htmlspecialchars($fetch_products['image']); ?>"
                     alt=""
                     class="w-full h-full object-cover" />
              <?php else: ?>
                <i class="fas fa-image text-gray-400 text-4xl"></i>
              <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="p-4 space-y-2">
              <div class="flex items-start justify-between gap-2">
                <h4 class="font-semibold line-clamp-2"><?= htmlspecialchars($fetch_products['name']); ?></h4>
                <span class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700">
                  <?= htmlspecialchars($fetch_products['category']); ?>
                </span>
              </div>

              <div class="text-sm text-gray-600 line-clamp-3" title="<?= htmlspecialchars($fetch_products['details']); ?>">
                <?= htmlspecialchars($fetch_products['details']); ?>
              </div>

              <div class="flex items-center gap-2 pt-1">
                <?php if($isLive): ?>
                  <span class="text-sm text-gray-500 line-through">$<?= number_format($base,2); ?></span>
                  <span class="text-green-700 font-semibold">$<?= number_format($final,2); ?></span>
                  <?php if($final > 0 && $base > 0): ?>
                    <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">
                      SAVE <?= round((($base-$final)/$base)*100); ?>%
                    </span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-gray-800 font-semibold">$<?= number_format($base,2); ?></span>
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
                    <label class="block text-xs text-gray-600 mb-1">Starts At (YYYY-MM-DD HH:MM:SS)</label>
                    <input type="text" name="starts_at"
                           value="<?= $promo && !empty($promo['starts_at']) ? htmlspecialchars($promo['starts_at']) : ''; ?>"
                           class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Ends At (YYYY-MM-DD HH:MM:SS)</label>
                    <input type="text" name="ends_at"
                           value="<?= $promo && !empty($promo['ends_at']) ? htmlspecialchars($promo['ends_at']) : ''; ?>"
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
