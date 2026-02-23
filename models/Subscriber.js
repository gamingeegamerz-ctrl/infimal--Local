const subscriberSchema = new mongoose.Schema({
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
    email: { type: String, required: true },
    name: String,
    status: { 
        type: String, 
        enum: ['active', 'inactive', 'bounced', 'unsubscribed'],
        default: 'active'
    },
    subscribedAt: { type: Date, default: Date.now },
    lastOpened: Date,
    lastClicked: Date,
    totalOpens: { type: Number, default: 0 },
    totalClicks: { type: Number, default: 0 },
    customFields: Map,
    tags: [String]
});
