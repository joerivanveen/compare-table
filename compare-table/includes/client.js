/* for each compare-table that might exist, go ahead and make it interactive */
function ruigehond014_compare_tables() {
    const tables = document.querySelectorAll('[data-ruigehond014]');
    tables.forEach(function (table) {
        const table_data = JSON.parse(table.dataset.ruigehond014);
        console.warn(table_data);
        /* startup the select lists in the table headers */

        /* make hovers in cells */
        const cells = table.querySelectorAll('td');
        cells.forEach(function (cell) {
            cell.addEventListener('mouseenter', function () {
                const description = this.querySelector('.description');
                if (description) description.classList.add('active');
            });
            cell.addEventListener('mouseleave', function () {
                const description = this.querySelector('.description');
                if (description) description.classList.remove('active');
            });
        });
    });
}

/* only after everything is locked and loaded we’re initialising */
if ('complete' === document.readyState) {
    ruigehond014_compare_tables();
} else {
    window.addEventListener('load', function () {
        ruigehond014_compare_tables();
    });
}
