/* for each compare-table that might exist, go ahead and make it interactive */
function ruigehond014_compare_tables() {
    const tables = document.querySelectorAll('[data-ruigehond014]');
    tables.forEach(function (table) {
        const table_data = JSON.parse(table.dataset.ruigehond014);
        let cross_haired = null;
        const do_cross_hair = function (td) {
            if (! td) { /* remove all cross-hairs */
                const elements = table.querySelectorAll('.cross-haired');
                const len = elements.length;
                for (let i = 0; i < len; i++) {
                    elements[i].classList.remove('cross-haired');
                }
                return;
            }
            // switch off all columns, except this column
            const active_row = td.parentNode;
            let index = 0;
            const length = active_row.children.length;
            for (let i = 0; i < length; i++) {
                if (active_row.children[i] === td) {
                    if (0 === i) {
                        index = -1; // first column is never highlighted
                    } else {
                        index = i;
                    }
                    break;
                }
            }
            // switch all rows off, except this row
            const rows = table.querySelectorAll('tr');
            const len = rows.length;
            for (let i = 0; i < len; i++) {
                const row = rows[i];
                if (row === active_row) {
                    row.classList.add('cross-haired');
                } else {
                    row.classList.remove('cross-haired');
                }
                // and the cells
                const tdlen = row.children.length;
                for (let tdi = 0; tdi < tdlen; tdi++) {
                    if (tdi === index) {
                        row.children[tdi].classList.add('cross-haired');
                    } else {
                        row.children[tdi].classList.remove('cross-haired');
                    }
                }
            }
            cross_haired = td;
        }
        console.warn(table_data);
        /* startup the select lists in the table headers */

        /* make info hovers in cells */
        const cells = table.querySelectorAll('td');
        cells.forEach(function (cell) {
            cell.addEventListener('mouseenter', function () {
                const description = this.querySelector('.description');
                if (description) description.classList.add('active');
                /* also: */
                do_cross_hair(this);
            });
            cell.addEventListener('mouseleave', function () {
                const description = this.querySelector('.description');
                if (description) description.classList.remove('active');
            });
        });
        /* remove crosshair */
        table.addEventListener('mouseleave', function () {
            do_cross_hair(null);
        });
    });
}

function ruigehond014_selector(el) {

}

/* only after everything is locked and loaded we’re initialising */
if ('complete' === document.readyState) {
    ruigehond014_compare_tables();
} else {
    window.addEventListener('load', function () {
        ruigehond014_compare_tables();
    });
}
