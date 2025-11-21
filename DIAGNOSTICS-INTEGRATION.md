# OCPay Diagnostics Integration

## Overview
تم دمج صفحة التشخيص (Diagnostics) مع صفحة الإعدادات الرئيسية لتوفير واجهة موحدة لإدارة ومراقبة نظام الدفع OCPay.

## التحديثات المنفذة

### 1. ملف class-ocpay-settings.php

#### دوال جديدة تم إضافتها:

**run_health_checks()**
- فحص 7 نقاط صحة رئيسية:
  - تفعيل WooCommerce
  - تمكين بوابة الدفع
  - تكوين مفتاح API
  - عمل WordPress Cron
  - جدولة Main Cron
  - جدولة Recent Orders Cron
  - تفعيل SSL
- ترجع مصفوفة تحتوي على حالة كل فحص

**get_system_stats()**
- جمع إحصائيات الطلبات:
  - عدد الطلبات المعلقة (Pending)
  - الطلبات الحديثة (أقل من 30 دقيقة)
  - الطلبات العالقة (أكثر من ساعة)
  - المعالجة اليوم
- استخدام دالة `get_orders_by_criteria()` للاستعلام

**get_orders_by_criteria($args)**
- دالة مساعدة لاستعلام الطلبات
- دعم HPOS (High-Performance Order Storage)
- Fallback إلى WC_Order_Query

**get_cron_status()**
- الحصول على حالة 3 مهام Cron:
  - Main Status Check (كل 20 دقيقة)
  - Recent Orders Check (كل 5 دقائق)
  - Stuck Orders Check (كل 30 دقيقة)
- عرض وقت التشغيل التالي والأخير

**get_last_cron_run($hook)**
- الحصول على آخر وقت تشغيل لمهمة Cron محددة
- عرض الوقت بصيغة human-readable

**test_cron_functionality()**
- اختبار وظائف WordPress Cron
- التحقق من عدم تعطيل DISABLE_WP_CRON

#### معالجات AJAX:

**ajax_test_connection()**
- اختبار اتصال API
- يستخدم OCPay_API_Client->test_connection()

**ajax_run_health_check()**
- تشغيل فحوصات الصحة
- إرجاع نسبة النجاح والتفاصيل

**ajax_get_stats()**
- تحديث إحصائيات الطلبات

**ajax_test_cron()**
- اختبار حالة Cron Jobs

### 2. ملف admin.js

#### معالجات JavaScript جديدة:

**#ocpay-run-health-check**
- تشغيل فحص الصحة
- تحديث النسبة المئوية
- تغيير اللون بناءً على النتيجة (أخضر/برتقالي/أحمر)

**#ocpay-refresh-stats**
- تحديث الإحصائيات
- تحديث 4 بطاقات الإحصائيات

**#ocpay-test-cron**
- اختبار Cron Jobs
- إعادة تحميل الصفحة لعرض التحديثات

### 3. ملف admin.css

#### أنماط جديدة:

**بطاقة الصحة (Health Card)**
- `.ocpay-health-summary` - خلفية متدرجة
- `.ocpay-health-score` - عرض النسبة المئوية
- `.health-check` - عناصر الفحص الفردية
- حالات: `healthy`, `warning`, `critical`

**بطاقة الإحصائيات (Stats Card)**
- `.ocpay-stats-grid` - شبكة 2x2
- `.stat-item` - بطاقات فردية بخلفيات متدرجة مختلفة
- تصميم responsive

**بطاقة Cron Jobs**
- `.ocpay-cron-list` - قائمة المهام
- `.cron-item` - عناصر فردية
- حالات: `active`, `inactive`
- عرض تفاصيل التوقيت

### 4. واجهة المستخدم (Dashboard)

#### البطاقات المعروضة:

1. **System Health** - عرض 7 فحوصات صحية مع نسبة مئوية
2. **Payment Statistics** - 4 إحصائيات للطلبات
3. **Cron Jobs Status** - حالة 3 مهام مع التوقيت
4. **API Connection Test** - اختبار الاتصال بـ API
5. **Quick Actions** - إجراءات سريعة (فحص يدوي، مسح السجلات)
6. **Activity Logs** - عرض سجلات النشاط

## الميزات الرئيسية

### ✅ مراقبة الصحة في الوقت الفعلي
- فحوصات شاملة للنظام
- تحديثات فورية عبر AJAX
- تصميم بصري واضح

### ✅ إحصائيات مفصلة
- تتبع الطلبات المعلقة
- رصد الطلبات العالقة
- إحصائيات يومية

### ✅ مراقبة Cron Jobs
- عرض حالة كل مهمة
- توقيت التشغيل التالي
- آخر وقت تشغيل

### ✅ إجراءات سريعة
- فحص يدوي للطلبات
- اختبار اتصال API
- مسح السجلات

### ✅ تصميم Responsive
- يعمل على جميع الشاشات
- شبكة تتكيف تلقائياً
- ألوان واضحة ومريحة

## التوافق

- ✅ WordPress 5.0+
- ✅ WooCommerce 5.0+
- ✅ PHP 7.4+
- ✅ HPOS (High-Performance Order Storage)
- ✅ معايير WordPress Coding Standards

## الأمان

- ✅ فحص nonce في جميع طلبات AJAX
- ✅ التحقق من صلاحيات `manage_woocommerce`
- ✅ sanitization وescaping لجميع المدخلات
- ✅ استخدام prepared statements

## الاختبار

تم اختبار جميع الوظائف:
- ✅ لا توجد أخطاء PHP
- ✅ لا توجد أخطاء JavaScript
- ✅ لا توجد أخطاء CSS
- ✅ جميع معالجات AJAX تعمل
- ✅ التصميم responsive

## الملفات المعدلة

1. `/includes/class-ocpay-settings.php` - إضافة دوال التشخيص ومعالجات AJAX
2. `/assets/js/admin.js` - إضافة معالجات JavaScript للأزرار الجديدة
3. `/assets/css/admin.css` - إضافة أنماط التصميم

## الملفات المحذوفة

- `/includes/class-ocpay-diagnostics.php` - تم دمجها في Settings

## ملاحظات مهمة

1. **Nonce**: جميع طلبات AJAX تستخدم `ocpay_admin_nonce`
2. **Permissions**: تحتاج صلاحية `manage_woocommerce`
3. **AJAX Actions**: مسجلة في constructor
4. **Cron Tracking**: يستخدم `ocpay_last_cron_run_` prefix

## الخطوات التالية للتطوير المستقبلي

- [ ] إضافة رسوم بيانية للإحصائيات
- [ ] تصدير التقارير PDF
- [ ] إشعارات البريد الإلكتروني للطلبات العالقة
- [ ] تكامل مع Slack/Telegram للإشعارات
- [ ] لوحة تحكم متقدمة مع تحليلات

---

**تم التنفيذ بنجاح ✓**
**التاريخ:** 2024
**الإصدار:** 1.2.1
