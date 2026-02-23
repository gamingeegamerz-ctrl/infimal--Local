const analyticsSchema = new mongoose.Schema({
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
    date: { type: Date, default: Date.now, index: true },
    
    // Daily counters
    emailsSent: { type: Number, default: 0 },
    emailsOpened: { type: Number, default: 0 },
    emailsClicked: { type: Number, default: 0 },
    emailsBounced: { type: Number, default: 0 },
    newSubscribers: { type: Number, default: 0 },
    
    // Real-time tracking
    activeCampaigns: { type: Number, default: 0 },
    
    // Performance metrics
    openRate: Number,
    clickRate: Number,
    bounceRate: Number,
    
    // Hourly breakdown for today (for live updates)
    hourlyData: [{
        hour: Number,
        sent: Number,
        opens: Number,
        clicks: Number
    }]
}, {
    timestamps: true
});

// Create indexes for fast queries
analyticsSchema.index({ userId: 1, date: 1 });
