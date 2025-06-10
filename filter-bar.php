<!-- ----------------------------------------------------------------
    filter-bar.php -->
<!-- ----------------------------------------------------------------
    Property Filter Bar
    -------------------
    Provides a sticky, responsive filter/search bar for property listing pages.
    Features:
    - Text search, property type, listing type, min/max price filters
    - Collapsible on small screens (Bootstrap collapse)
    - Remembers selected values after submission
    - Form submits via GET to current page for server-side filtering
    - Bootstrap-based grid for responsive layout
    Dependencies:
    - Bootstrap 5
-->

<div class="bg-white shadow-sm border-bottom py-3 px-3 sticky-top" style="z-index: 1020;">
  <!-- 
    On small screens, show a button to toggle the filter form. 
    On medium and larger, form is always visible.
  -->
  <div class="d-md-none mb-3">
    <button class="btn btn-outline-primary w-100 custom-btn" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
      Filter properties
    </button>
  </div>

  <!-- 
    The filter form is wrapped in a Bootstrap .collapse for mobile UX.
    On desktop, .d-md-block ensures it's always open.
  -->
  <div class="collapse d-md-block" id="filterCollapse">
    <form 
      method="GET"
      action="<?= basename($_SERVER['PHP_SELF']) ?>"
      class="row gx-3 gy-2 align-items-stretch"
    >
      <!-- Search box -->
      <div class="col-12 col-md-3 mb-2 mb-md-0">
        <input
          type="text"
          name="search"
          class="form-control"
          placeholder="Search..."
          value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
        >
      </div>

      <!-- Property type dropdown -->
      <div class="col-6 col-md-2 mb-2 mb-md-0">
        <select name="property_type" class="form-select">
          <option value="">Type</option>
          <?php foreach (['house', 'apartment', 'office', 'land'] as $type): ?>
            <option value="<?= $type ?>" <?= (($_GET['property_type'] ?? '') === $type) ? 'selected' : '' ?>>
              <?= ucfirst($type) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Listing type dropdown (rent/sale) -->
      <div class="col-6 col-md-2 mb-2 mb-md-0">
        <select name="listing_type" class="form-select">
          <option value="">Listing</option>
          <?php foreach (['rent', 'sale'] as $listing): ?>
            <option value="<?= $listing ?>" <?= (($_GET['listing_type'] ?? '') === $listing) ? 'selected' : '' ?>>
              <?= ucfirst($listing) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Minimum price input -->
      <div class="col-6 col-md-2 mb-2 mb-md-0">
        <input
          type="number"
          name="min_price"
          class="form-control"
          placeholder="Min Price"
          value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>"
        >
      </div>

      <!-- Maximum price input -->
      <div class="col-6 col-md-2 mb-2 mb-md-0">
        <input
          type="number"
          name="max_price"
          class="form-control"
          placeholder="Max Price"
          value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"
        >
      </div>

      <!-- Filter button -->
      <div class="col-12 col-md-1"> 
        <button class="btn btn-sm w-100 custom-btn">Filter</button>
      </div>
    </form>
  </div>
</div>
