<form id="registerForm">
  <div class="mb-3">
    <label class="form-label">First Name *</label>
    <input type="text" name="first_name" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Last Name *</label>
    <input type="text" name="last_name" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Phone Number</label>
    <input type="text" name="phone" class="form-control">
  </div>

  <div class="mb-3">
    <label class="form-label">Email *</label>
    <input type="email" name="email" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Password *</label>
    <input type="password" name="password" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Confirm Password *</label>
    <input type="password" name="confirm" class="form-control" required>
  </div>
  
  <h5 class="text-muted">Optional Preferences</h5>
  
  <div class="mb-3">
    <label class="form-label">Preferred Minimum Price ($)</label>
    <input type="number" name="minprice" class="form-control" min="0">
  </div>

  <div class="mb-3">
    <label class="form-label">Preferred Maximum Price ($)</label>
    <input type="number" name="maxprice" class="form-control" min="0">
  </div>

  <div class="mb-3">
    <label class="form-label">Minimum Bedrooms</label>
    <input type="number" name="minbeds" class="form-control" min="0">
  </div>

  <div class="mb-3">
    <label class="form-label">Minimum Bathrooms</label>
    <input type="number" name="minbaths" class="form-control" min="0">
  </div>

  <div class="mb-3">
    <label class="form-label">Preferred City</label>
    <select class="form-select" name="preferredcity">
      <option value="">-- Select City --</option>
      <?php foreach ($cities as $city): ?>
        <option value="<?= $city ?>"><?= $city ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  
  <div id="reg-message" class="text-danger mb-3"></div>

  <button type="submit" class="btn btn-primary w-100">Register</button>
</form>