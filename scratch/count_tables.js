const fs = require('fs');

['accounts_dashboard.php','admin_dashboard.php'].forEach(f => {
  const c = fs.readFileSync(f,'utf8');
  const tables = (c.match(/<table/g) || []).length;
  const wrapped = (c.match(/table-wrap/g) || []).length;
  console.log(f + ': tables=' + tables + '  table-wraps=' + wrapped);
});
