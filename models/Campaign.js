const campaignSchema = new mongoose.Schema({
    userId: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
    name: { type: String, required: true },
    subject: String,
    status: { 
        type: String, 
        enum: ['draft', 'scheduled', 'sending', 'sent', 'cancelled'],
        default: 'draft'
    },
    sentAt: Date,
    totalSent: { type: Number, default: 0 },
    opens: { type: Number, default: 0 },
    clicks: { type: Number, default: 0 },
    bounces: { type: Number, default: 0 },
    unsubscribes: { type: Number, default: 0 },
    createdAt: { type: Date, default: Date.now }
});
