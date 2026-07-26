const axios = require('axios');

const api = axios.create({ baseURL: 'http://127.0.0.1:8000/api' });
let token = '';

async function runTests() {
  console.log('Testing APIs...');
  
  try {
    // 1. Categories
    const catRes = await api.get('/categories');
    console.log('[PASS] Categories listing:', catRes.data.data.items.length, 'categories found');
    
    // 2. Products
    const prodRes = await api.get('/products');
    console.log('[PASS] Product listing:', prodRes.data.data.items.length, 'products found');
    
    // 3. Login as Customer
    const loginRes = await api.post('/auth/login', {
      email: 'customer@example.com',
      password: 'password'
    });
    token = loginRes.data.data.token;
    console.log('[PASS] Login as customer');
    api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    
    // 4. Cart
    await api.post('/cart/items', { product_id: prodRes.data.data.items[0].id, quantity: 1 });
    const cartRes = await api.get('/cart');
    console.log('[PASS] Added to cart, total items:', cartRes.data.data.items_count);
    
    // 5. Checkout
    const orderRes = await api.post('/checkout', {}, { headers: { 'Idempotency-Key': 'test-key-1' } });
    console.log('[PASS] Checkout, order ID:', orderRes.data.data.id);
    
    // 6. Login as Admin
    const adminLogin = await api.post('/auth/login', {
      email: 'admin@marketplace.test',
      password: 'password'
    });
    api.defaults.headers.common['Authorization'] = `Bearer ${adminLogin.data.data.token}`;
    console.log('[PASS] Login as Admin');
    
    // 7. Admin Vendors
    const vendorsRes = await api.get('/admin/vendors');
    console.log('[PASS] Admin Vendors listing:', vendorsRes.data.data.items.length, 'vendors found');

    // 8. Login as Vendor
    const vendorLogin = await api.post('/auth/login', {
      email: 'vendor-a@example.com',
      password: 'password'
    });
    api.defaults.headers.common['Authorization'] = `Bearer ${vendorLogin.data.data.token}`;
    console.log('[PASS] Login as Vendor A');

    // 9. Vendor Products
    const vendorProdRes = await api.get('/vendor/products');
    console.log('[PASS] Vendor Products listing:', vendorProdRes.data.data.items.length, 'products found');

  } catch (error) {
    console.error('[FAIL]', error.response ? error.response.data : error.message);
  }
}

runTests();
