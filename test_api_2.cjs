const axios = require('axios');
const fs = require('fs');

const api = axios.create({ baseURL: 'http://127.0.0.1:8000/api' });

async function runTests() {
  console.log('Testing APIs Phase 2...');
  try {
    // Login as Vendor A
    const vendorLogin = await api.post('/auth/login', {
      email: 'vendor-a@example.com',
      password: 'password'
    });
    api.defaults.headers.common['Authorization'] = `Bearer ${vendorLogin.data.data.token}`;
    
    // Create Product
    const newProd = await api.post('/vendor/products', {
      category_id: 1,
      name: 'Test Prod',
      price: 15.50,
      stock: 10,
      status: 'active'
    });
    console.log('[PASS] Create Vendor Product:', newProd.data.data.name);

    // Apply Vendor (For Customer, but Vendor-A already applied. We need to create a new user to test apply)
    const newUser = await api.post('/auth/register', {
        name: 'New Vendor',
        email: 'newvendor@example.com',
        password: 'password',
        password_confirmation: 'password'
    });
    console.log('[PASS] Register New Vendor User');

    const newApi = axios.create({ baseURL: 'http://127.0.0.1:8000/api' });
    newApi.defaults.headers.common['Authorization'] = `Bearer ${newUser.data.data.token}`;
    newApi.defaults.headers.common['Idempotency-Key'] = 'test-key-2';

    const applied = await newApi.post('/vendor/apply', {
        store_name: 'New Store Test',
        payout_details: { bank_name: 'Test Bank', iban: '123' }
    });
    console.log('[PASS] Vendor Apply:', applied.data.data.store_name);

  } catch (error) {
    console.error('[FAIL]', error.response ? error.response.data : error.message);
  }
}
runTests();
