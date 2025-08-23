<?php
@include 'config.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){
   header('location:login.php');
   exit;
}

$message = $message ?? [];

/* =========================================================
   CATEGORY RESOLUTION (accepts ?category=ID or name)
========================================================= */
$category_param = isset($_GET['category']) ? trim($_GET['category']) : '';
$category_param = filter_var($category_param, FILTER_SANITIZE_STRING);

$category_id = null;
$category_label = '';

try {
   if ($category_param !== '') {
      if (ctype_digit($category_param)) {
         $cid = (int)$category_param;
         $stmt = $conn->prepare("SELECT name FROM `categories` WHERE id = ? LIMIT 1");
         $stmt->execute([$cid]);
         $name = $stmt->fetchColumn();
         if ($name) { $category_id = $cid; $category_label = $name; }
      } else {
         $needle = strtolower(preg_replace('/\s+/', '', $category_param));
         $stmt = $conn->prepare("SELECT id, name FROM `categories` WHERE LOWER(REPLACE(name,' ','')) = ? LIMIT 1");
         $stmt->execute([$needle]);
         if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $category_id = (int)$row['id']; $category_label = $row['name'];
         }
      }
   }
} catch (Exception $e) { /* ignore */ }

/* =========================================================
   Category list for search suggestions (datalist)
========================================================= */
$category_names = [];
try {
   $cq = $conn->query("SELECT name FROM `categories` ORDER BY name ASC");
   if ($cq) { $category_names = $cq->fetchAll(PDO::FETCH_COLUMN); }
} catch (Exception $e) {}

/* =========================
   Add to WISHLIST (trust pid)
========================= */
if(isset($_POST['add_to_wishlist'])){
   $pid = (int)($_POST['pid'] ?? 0);

   $pstmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ? LIMIT 1");
   $pstmt->execute([$pid]);
   $prod = $pstmt->fetch(PDO::FETCH_ASSOC);

   if(!$prod){
      $message[] = 'Product not found.';
   }else{
      $checkW = $conn->prepare("SELECT 1 FROM wishlist WHERE pid = ? AND user_id = ? LIMIT 1");
      $checkW->execute([$pid, $user_id]);

      $checkC = $conn->prepare("SELECT 1 FROM cart WHERE pid = ? AND user_id = ? LIMIT 1");
      $checkC->execute([$pid, $user_id]);

      if($checkW->rowCount() > 0){
         $message[] = 'Already in wishlist!';
      }elseif($checkC->rowCount() > 0){
         $message[] = 'Already in cart!';
      }else{
         $ins = $conn->prepare("INSERT INTO wishlist (user_id, pid, name, price, image) VALUES (?,?,?,?,?)");
         $ins->execute([$user_id, $prod['id'], $prod['name'], $prod['price'], $prod['image']]);
         $message[] = 'Added to wishlist!';
      }
   }
}

/* =========================
   Add to CART with stock subtraction
========================= */
if(isset($_POST['add_to_cart'])){
   $pid  = (int)($_POST['pid'] ?? 0);
   $reqQ = max(1, (int)($_POST['p_qty'] ?? 1));

   try{
      $conn->beginTransaction();

      $pstmt = $conn->prepare("SELECT id, name, price, image, quantity FROM products WHERE id = ? FOR UPDATE");
      $pstmt->execute([$pid]);
      $prod = $pstmt->fetch(PDO::FETCH_ASSOC);

      if(!$prod){
         $conn->rollBack();
         $message[] = 'Product not found.';
      }elseif((int)$prod['quantity'] <= 0){
         $conn->rollBack();
         $message[] = 'Out of stock.';
      }else{
         $avail   = (int)$prod['quantity'];
         $addQty  = min($reqQ, $avail);
         $newStock = $avail - $addQty;

         $csel = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND pid = ? FOR UPDATE");
         $csel->execute([$user_id, $pid]);

         if($row = $csel->fetch(PDO::FETCH_ASSOC)){
            $newCartQty = (int)$row['quantity'] + $addQty;
            $cupd = $conn->prepare("UPDATE cart SET quantity = ?, price = ?, name = ?, image = ? WHERE id = ?");
            $cupd->execute([$newCartQty, $prod['price'], $prod['name'], $prod['image'], $row['id']]);
         }else{
            $cins = $conn->prepare("INSERT INTO cart (user_id, pid, name, price, quantity, image) VALUES (?,?,?,?,?,?)");
            $cins->execute([$user_id, $prod['id'], $prod['name'], $prod['price'], $addQty, $prod['image']]);
         }

         $up = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
         $up->execute([$newStock, $pid]);

         $conn->commit();

         if($addQty < $reqQ){
            $message[] = "Only {$addQty} left; added {$addQty} to cart.";
         }else{
            $message[] = 'Added to cart!';
         }
      }
   }catch(Exception $e){
      if($conn->inTransaction()){ $conn->rollBack(); }
      $message[] = 'Could not add to cart. Please try again.';
   }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>category</title>

   <!-- Tailwind CDN -->
   <script src="https://cdn.tailwindcss.com"></script>
   <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: '#8B4513',
              secondary: '#A0522D',
              accent: '#D2B48C',
              dark: '#3E2723',
              darker: '#1B0F0A'
            },
            fontFamily: { gaming: ['Orbitron','monospace'] }
          }
        }
      }
   </script>

   <!-- font awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- site css -->
   <link rel="stylesheet" href="css/style.css">

   <style>
      *{box-sizing:border-box}
      body{
         font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans";
         background: linear-gradient(135deg,#1B0F0A 0%,#3E2723 50%,#5D4037 100%);
         color:#fff; overflow-x:hidden;
      }
      .hero-bg{
        background:
          radial-gradient(circle at 20% 80%, rgba(139,69,19,.35) 0%, transparent 55%),
          radial-gradient(circle at 80% 20%, rgba(210,180,140,.35) 0%, transparent 55%),
          radial-gradient(circle at 40% 40%, rgba(160,82,45,.35) 0%, transparent 55%);
      }
      .glass-effect{ background:rgba(255,255,255,.08); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.18) }
      .neon-glow{ box-shadow:0 0 20px rgba(139,69,19,.5),0 0 40px rgba(160,82,45,.3),0 0 60px rgba(210,180,140,.2) }
      .hover-glow:hover{ transform:translateY(-5px); box-shadow:0 10px 25px rgba(139,69,19,.35); transition:all .3s ease }
      .gradient-text{ background: linear-gradient(45deg,#8B4513,#A0522D,#D2B48C); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text }
      .floating-animation{ animation:floating 3s ease-in-out infinite }
      @keyframes floating{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

      /* -------- Product cards -------- */
      .product-card{
        background: linear-gradient(180deg, rgba(62,39,35,.92), rgba(62,39,35,.82));
        border:1px solid rgba(210,180,140,.28);      /* constant border to avoid subpixel shifts */
        border-radius:22px;
        backdrop-filter: blur(16px);
        position:relative; overflow:hidden;

        /* FIX: prevent grid color/flicker & neighbor influence */
        isolation:isolate;            /* isolate blending to this card */
        contain: paint;               /* confine painting to this element */
        backface-visibility:hidden;
        transform: translateZ(0);     /* promote to its own layer */
        will-change: transform;       /* hint GPU */

        transition: transform .35s ease, box-shadow .35s ease; /* removed border-color change */
      }
      .product-card:hover{
        transform: translateY(-6px);  /* removed scale to avoid overlapping neighbors */
        box-shadow: 0 22px 48px rgba(160,82,45,.35);
      }

      .product-card .aspect-square{
        border-radius:18px; overflow:hidden; border:1px solid rgba(210,180,140,.25);
        background: radial-gradient(600px 120px at 20% 0%, rgba(210,180,140,.18), transparent 60%);
        backface-visibility:hidden; transform: translateZ(0); will-change: transform;
      }
      .product-card img{
        transition: transform .6s ease;
        backface-visibility:hidden; transform: translateZ(0); will-change: transform;
      }
      .group:hover .product-card img{ transform: scale(1.05) } /* gentler scale to reduce repaint load */

      .price-badge{
        font-size:1.05rem; padding:.55rem 1rem; border:1px solid rgba(255,255,255,.18);
        background: linear-gradient(135deg,#8B4513,#D2B48C); color:#fff; border-radius:9999px;
        box-shadow:0 6px 18px rgba(210,180,140,.25)
      }
      .product-title{ color:#FFF7EE; font-weight:800; letter-spacing:.2px; line-height:1.25; text-shadow:0 1px 0 rgba(0,0,0,.35) }
      .qty{ background: rgba(255,255,255,.08); color:#fff }
      .qty:focus{ outline:none; box-shadow:0 0 0 3px rgba(210,180,140,.35) }
      .badge-stock{position:absolute;top:6px;right:6px}
      .oos-overlay{position:absolute;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;font-weight:800;letter-spacing:.5px}
   </style>
</head>
<body>
   
<?php include 'header.php'; ?>

<section class="relative py-16 md:py-20 hero-bg overflow-hidden">
  <div class="absolute top-10 left-10 w-72 h-72 bg-gradient-to-r from-[rgba(139,69,19,0.2)] to-[rgba(210,180,140,0.2)] rounded-full blur-3xl floating-animation"></div>
  <div class="absolute bottom-10 right-10 w-64 h-64 bg-gradient-to-r from-[rgba(160,82,45,0.2)] to-[rgba(139,69,19,0.2)] rounded-full blur-3xl floating-animation" style="animation-delay:1s"></div>

  <div class="container mx-auto px-6 lg:px-12 relative z-10">
    <div class="glass-effect rounded-3xl p-8 md:p-10 neon-glow">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div>
          <h1 class="text-4xl md:text-5xl font-bold mb-2">
            <span class="gradient-text font-gaming">CATEGORY</span>
          </h1>
          <p class="text-gray-200">
            <?= $category_label ? htmlspecialchars($category_label) : 'All Products'; ?>
          </p>
        </div>

        <!-- Category Search (uses ?category=...) -->
        <form action="" method="get" class="w-full md:w-auto">
          <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative">
              <i class="fas fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300"></i>
              <input
                type="text"
                name="category"
                list="categoryList"
                value="<?= htmlspecialchars($category_param); ?>"
                placeholder="Search category (e.g., Wood, Brass)..."
                class="w-full sm:w-80 pl-10 pr-3 py-2 rounded-xl glass-effect border border-white/20 focus:outline-none focus:ring-2 focus:ring-[rgba(139,69,19,0.7)]"
              />
              <datalist id="categoryList">
                <?php foreach($category_names as $cn): ?>
                  <option value="<?= htmlspecialchars($cn); ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="flex gap-2">
              <button class="inline-flex items-center gap-2 px-5 py-2 rounded-xl btn-grad hover-glow" type="submit" style="background:linear-gradient(135deg,#8B4513,#D2B48C);color:#fff;">
                <i class="fas fa-search"></i> Search
              </button>
              <a href="category.php" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl glass-effect hover-glow">
                <i class="fas fa-rotate-left"></i> Reset
              </a>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
</section>

<section id="products" class="py-16">
  <div class="container mx-auto px-6 lg:px-12">

    <?php if(!empty($message)): ?>
      <div class="max-w-3xl mx-auto mb-8 space-y-2">
        <?php foreach($message as $m): ?>
          <div class="bg-amber-100 text-amber-900 border border-amber-300 px-4 py-2 rounded"><?= htmlspecialchars($m) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10">
      <?php
        if ($category_id !== null) {
           $select_products = $conn->prepare("SELECT * FROM `products` WHERE category_id = ? ORDER BY id DESC");
           $select_products->execute([$category_id]);
        } else {
           $select_products = $conn->prepare("SELECT * FROM `products` ORDER BY id DESC");
           $select_products->execute();
        }

        if($select_products->rowCount() > 0){
          while($fetch_products = $select_products->fetch(PDO::FETCH_ASSOC)){
            $pid   = (int)$fetch_products['id'];
            $price = (float)$fetch_products['price'];
            $qty   = (int)($fetch_products['quantity'] ?? 0);
            $inStock = $qty > 0;
      ?>
      <form action="" method="POST" class="group">
        <div class="product-card p-6 relative h-full flex flex-col">
          <!-- Price badge -->
          <div class="absolute top-6 left-6 price-badge z-10">
            Rs <?= number_format($price, 2); ?>/-
          </div>

          <!-- Stock badge (show only when < 10; hide for 10+) -->
          <div class="badge-stock z-10">
            <?php if(!$inStock): ?>
              <span class="text-xs px-2 py-1 rounded bg-rose-100 text-rose-800">Out of stock</span>
            <?php elseif($qty < 10): ?>
              <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-800">Only <?= $qty; ?> left</span>
            <?php endif; ?>
          </div>

          <!-- Quick actions -->
          <div class="absolute top-6 right-6 flex gap-2 z-10">
            <button type="submit" name="add_to_wishlist" title="Add to wishlist"
                    class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C] transition"
                    aria-label="Add to wishlist">
              <i class="fas fa-heart"></i>
            </button>
            <a href="view_page.php?pid=<?= $pid; ?>"
               class="w-11 h-11 glass-effect rounded-full flex items-center justify-center hover:text-white hover:bg-gradient-to-r hover:from-[#8B4513] hover:to-[#D2B48C] transition"
               aria-label="View">
               <i class="fas fa-eye"></i>
            </a>
          </div>

          <!-- Image -->
          <div class="aspect-square mb-6 relative">
            <img src="uploaded_img/<?= htmlspecialchars($fetch_products['image']); ?>"
                 alt="<?= htmlspecialchars($fetch_products['name']); ?>"
                 class="w-full h-full object-cover">
            <?php if(!$inStock): ?>
              <div class="oos-overlay text-white text-lg rounded">OUT OF STOCK</div>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="space-y-4 mt-auto">
            <h3 class="product-title text-xl"><?= htmlspecialchars($fetch_products['name']); ?></h3>

            <!-- Hidden inputs (server trusts only pid) -->
            <input type="hidden" name="pid" value="<?= $pid; ?>">

            <!-- Qty (no max; server caps) -->
            <div class="flex items-center gap-3">
              <label class="text-sm font-medium text-gray-200">QTY:</label>
              <input type="number" min="1" value="<?= $inStock ? 1 : 0; ?>" name="p_qty"
                     class="qty w-24 px-3 py-2 rounded-lg text-center" <?= $inStock ? '' : 'disabled'; ?>>
            </div>

            <!-- Add to cart -->
            <button type="submit" name="add_to_cart"
                    class="w-full py-3.5 rounded-xl font-semibold hover-glow neon-glow transition"
                    style="background:linear-gradient(135deg,#8B4513,#D2B48C);color:#fff;"
                    <?= $inStock ? '' : 'disabled style="opacity:.6;cursor:not-allowed"'; ?>>
              <i class="fas fa-shopping-cart mr-2"></i> <?= $inStock ? 'Add to Cart' : 'Unavailable' ?>
            </button>
          </div>
        </div>
      </form>
      <?php
          }
        } else {
          echo '<div class="col-span-full">
                  <div class="glass-effect p-12 rounded-3xl text-center">
                    <i class="fas fa-box-open text-5xl" style="color:#CD853F"></i>
                    <p class="mt-4 text-xl text-gray-200">No products available!</p>
                  </div>
                </div>';
        }
      ?>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
<script src="js/script.js"></script>
</body>
</html>
