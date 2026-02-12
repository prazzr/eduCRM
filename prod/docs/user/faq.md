# eduCRM Frequently Asked Questions (FAQ)

## General Questions

### Q: What is eduCRM?
**A:** eduCRM is a comprehensive Customer Relationship Management system designed specifically for educational institutions. It helps manage student inquiries, applications, communications, and analytics.

### Q: Who can use eduCRM?
**A:** eduCRM is designed for:
- Educational consultancies
- Universities and colleges
- Training institutes
- Study abroad agencies
- Any organization managing student enrollments

---

## Getting Started

### Q: How do I log in for the first time?
**A:** Use the default credentials provided by your administrator. You'll be prompted to change your password on first login.

### Q: I forgot my password. What should I do?
**A:** Click "Forgot Password" on the login page and follow the instructions. Contact your administrator if you don't receive the reset email.

### Q: How do I change my password?
**A:** Go to your profile (click your name in top-right) → Settings → Change Password.

---

## Inquiries & Students

### Q: How do I add a new inquiry?
**A:** Navigate to Inquiries → Add Inquiry → Fill in the form → Save. Name, email, and phone are required fields.

### Q: What is lead scoring?
**A:** Lead scoring automatically assigns a score (0-100) to inquiries based on factors like source, program interest, and engagement. Higher scores indicate higher conversion probability.

### Q: How do I convert an inquiry to a student?
**A:** Open the inquiry → Click "Convert to Student" → Fill in additional details → Save.

### Q: Can I bulk import inquiries?
**A:** Yes, go to Inquiries → Bulk Actions → Import CSV. Download the template first to see the required format.

---

## Tasks & Appointments

### Q: How do I create a task?
**A:** Tasks → Add Task → Fill in title, description, due date, priority → Assign to user → Save.

### Q: Can I assign tasks to multiple people?
**A:** Currently, tasks can be assigned to one user at a time. Create separate tasks for multiple assignees.

### Q: How do I schedule an appointment?
**A:** Appointments → Add Appointment → Fill in details → Select date/time → Save.

### Q: Can I sync appointments with Google Calendar?
**A:** This feature is planned for a future update. Currently, you can export appointments as ICS files.

---

## Messaging

### Q: What messaging channels are supported?
**A:** eduCRM supports:
- SMS (Twilio, SMPP, Gammu)
- WhatsApp (Twilio, Meta, 360Dialog)
- Viber (Bot API)
- Email

### Q: How do I send a bulk message?
**A:** Messaging → Send Message → Select recipients (use filters or select all) → Choose channel → Compose message → Send.

### Q: Can I schedule messages for later?
**A:** This feature is planned for a future update.

### Q: How do I track message delivery?
**A:** Go to Messaging → Message History to see delivery status for all sent messages.

---

## Reports & Analytics

### Q: What reports are available?
**A:** Pre-built reports include:
- Inquiry Report
- Student Report
- Financial Report
- Counselor Performance Report

### Q: Can I export reports?
**A:** Yes, all reports can be exported as CSV, Excel, or PDF.

### Q: How do I access the analytics dashboard?
**A:** Click "Analytics" in the main menu. You'll see real-time KPIs, conversion funnel, revenue trends, and more.

### Q: Can I customize reports?
**A:** Yes, use the Report Builder to create custom reports with specific fields and filters.

---

## Documents

### Q: What file types can I upload?
**A:** Supported formats:
- Images: JPG, PNG, GIF
- Documents: PDF, DOC, DOCX, XLS, XLSX
- Maximum size: 5MB per file

### Q: Where are uploaded files stored?
**A:** Files are securely stored in the uploads directory with encrypted filenames.

### Q: Can I delete uploaded documents?
**A:** Yes, click the delete icon next to the document. Only admins and the uploader can delete files.

---

## Security

### Q: Is my data secure?
**A:** Yes, eduCRM implements:
- Password hashing
- CSRF protection
- SQL injection prevention
- XSS protection
- Secure file uploads
- Session security

### Q: What is 2FA and should I enable it?
**A:** Two-Factor Authentication adds an extra security layer. We highly recommend enabling it for admin accounts.

### Q: How often should I change my password?
**A:** We recommend changing passwords every 90 days. Use strong passwords with uppercase, lowercase, numbers, and special characters.

---

## Performance

### Q: Why is the system slow?
**A:** Common causes:
- Large number of records
- Slow internet connection
- Server resources
- Browser cache

**Solutions:**
- Clear browser cache
- Use date range filters
- Contact administrator for server optimization

### Q: Can I use eduCRM on mobile?
**A:** Yes, eduCRM is mobile-responsive. A dedicated mobile app is planned for future release.

---

## Troubleshooting

### Q: I can't log in. What should I do?
**A:** Check:
1. Username/password correct?
2. Caps Lock off?
3. Account not locked?
4. Contact administrator if issue persists

### Q: I'm getting "Permission Denied" errors
**A:** Your user role may not have access to that feature. Contact your administrator to request access.

### Q: Files won't upload
**A:** Check:
1. File size < 5MB?
2. File type allowed?
3. Internet connection stable?
4. Try different browser

### Q: Reports not generating
**A:** Try:
1. Reduce date range
2. Clear browser cache
3. Check if data exists for selected period
4. Contact administrator

---

## Administration

### Q: How do I add new users?
**A:** (Admin only) Users → Add User → Fill in details → Select role → Save.

### Q: What user roles are available?
**A:** 
- **Admin:** Full system access
- **Counselor:** Manage inquiries, students, messaging
- **Teacher:** LMS access, class management
- **Student:** View profile, classes, tasks
- **Accountant:** Financial management

### Q: How do I configure messaging gateways?
**A:** (Admin only) Messaging → Gateways → Add Gateway → Select provider → Enter credentials → Save.

### Q: Can I customize email templates?
**A:** This feature is planned for a future update.

---

## Billing & Payments

### Q: How do I record a payment?
**A:** Student Profile → Payments → Add Payment → Enter amount, method, date → Save.

### Q: Can students pay online?
**A:** Online payment integration is planned for a future update.

### Q: How do I generate invoices?
**A:** Go to Financial Report → Select student → Export as PDF.

---

## Data Management

### Q: How do I backup my data?
**A:** (Admin only) Automated backups run daily. Manual backup: Settings → Backup → Create Backup.

### Q: Can I export all my data?
**A:** Yes, use the Report Builder to export data by module.

### Q: How long is data retained?
**A:** Data is retained indefinitely unless manually deleted. Deleted items may be recoverable for 30 days (soft delete).

---

## Support

### Q: Where can I get help?
**A:** 
1. Check this FAQ
2. Read the User Guide
3. Watch video tutorials
4. Contact your administrator
5. Email support (if available)

### Q: How do I report a bug?
**A:** Contact your administrator with:
- What you were trying to do
- What happened
- Screenshots if possible
- Browser and device info

### Q: Can I request new features?
**A:** Yes! Contact your administrator with feature requests. We continuously improve eduCRM based on user feedback.

---

## Updates & Maintenance

### Q: How often is eduCRM updated?
**A:** Updates are released regularly with new features, improvements, and security patches.

### Q: Will I lose data during updates?
**A:** No, all data is preserved during updates. Backups are created automatically before updates.

### Q: Is there scheduled maintenance?
**A:** Maintenance is typically scheduled during off-peak hours. You'll be notified in advance.

---

## Best Practices

### Q: How can I improve my conversion rate?
**A:**
1. Follow up quickly on new inquiries
2. Use lead scoring to prioritize
3. Set reminders for follow-ups
4. Track all communications
5. Review analytics regularly

### Q: What's the best way to organize inquiries?
**A:**
1. Use consistent status updates
2. Add detailed notes
3. Create tasks for follow-ups
4. Use tags/categories
5. Regular pipeline review

### Q: How should I use the analytics dashboard?
**A:**
1. Check daily for new trends
2. Monitor conversion funnel
3. Track counselor performance
4. Set and review goals
5. Export reports for meetings

---

**Still have questions?**

Contact your administrator or check the complete User Manual for detailed information.

**Version:** 1.0  
**Last Updated:** 2026-01-01
