const express = require('express');
const router = express.Router();
const auth = require('../middleware/auth');
const AnalyticsController = require('../controllers/AnalyticsController');

// All routes require authentication
router.use(auth);

// Get dashboard data
router.get('/dashboard', AnalyticsController.getDashboardData);

// Real-time updates
router.get('/live-stats', AnalyticsController.updateLiveStats);

// Track email opens (called from tracking pixel)
router.get('/track/open', AnalyticsController.trackEmailOpen);

// Track email clicks
router.get('/track/click', AnalyticsController.trackEmailClick);

module.exports = router;
