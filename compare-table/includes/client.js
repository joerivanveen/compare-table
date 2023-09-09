/* for each compare-table that might exist, go ahead and make it interactive */
function ruigehond014_compare_tables() {
    const tables = document.querySelectorAll('[data-ruigehond014]');
    tables.forEach(function (table) {
        const table_data = JSON.parse(table.dataset.ruigehond014);
        const do_cross_hair = function (td) {
            if (!td) { /* remove all cross-hairs */
                const elements = table.querySelectorAll('.cross-haired');
                const len = elements.length;
                for (let i = 0; i < len; i++) {
                    elements[i].classList.remove('cross-haired');
                }
                return;
            }
            // find out which column we’re in
            const active_row = td.parentNode;
            let index = 0;
            for (let i = 0, len = active_row.children.length; i < len; i++) {
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
            for (let i = 0, len = rows.length; i < len; i++) {
                const row = rows[i];
                if (row === active_row) {
                    row.classList.add('cross-haired');
                } else {
                    row.classList.remove('cross-haired');
                }
                // and the cells: switch off all columns, except this column
                const tdlen = row.children.length;
                for (let tdi = 0; tdi < tdlen; tdi++) {
                    if (tdi === index) {
                        row.children[tdi].classList.add('cross-haired');
                    } else {
                        row.children[tdi].classList.remove('cross-haired');
                    }
                }
            }
        }
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
        /* remove cross-hair */
        table.addEventListener('mouseleave', function () {
            do_cross_hair(null);
        });
        /* startup the select lists in the table headers */
        console.warn(table_data);
        for (let prop in {'show_columns': 1, 'show_subjects': 1, 'all_subjects': 1, 'alphabetical': 1, 'rows': 1}) {
            if (!table_data.hasOwnProperty(prop)) {
                console.error(`${prop} missing from table_data.`);
                return;
            }
        }
        const select_lists = [];
        for (let i = 0, len = table_data.show_columns; i < len; i++) {
            const selector = new ruigehond014_selector(table_data.all_subjects, table_data.show_subjects, i);
            select_lists.push(selector);
            /* add to dom */
            const th = table.querySelector('.select.index' + i);
            if (th) {
                th.appendChild(selector.el);
            } else {
                console.error(`.select.index${i} missing from table.`);
            }
        }
        table_data.select_lists = select_lists;
    });
}

function ruigehond014_selector(subjects, selected, column_index) {
    const el = document.createElement('select');
    const self = this;
    el.classList.add('ruigehond014-selector');
    el.appendChild(document.createElement('option'));
    for (let i = 0, len = subjects.length; i < len; i++) {
        const option = document.createElement('option');
        const subject = subjects[i];
        option.value = subject;
        option.innerHTML = subject;
        if (selected.indexOf(subject) > -1) {
            option.hidden = true;
        }
        el.appendChild(option);
    }
    el.addEventListener('change', function () {
        self.select(this.options[this.selectedIndex].value);
    });
    this.el = el;
    this.column_index = column_index;
    return this;
}

ruigehond014_selector.prototype.select = function(subject) {
    const parts = window.location.href.split('?');
    if (parts.length > 1) {
        const params = parts[1].split('&');
        const len = params.length;
        for (let i = len - 1; i >= 0; i--) {
            if (0 === params[i].indexOf('compare-table-column-' + this.column_index)) {
                delete params[i];
            }
        }
        params.push('compare-table-column-' + this.column_index + '=' + encodeURIComponent(subject));
        parts[1] = Object.values(params).join('&');
        //parts[1] = params.filter(function(item) { return undefined !== item; }).join('&');
    } else {
        parts.push('compare-table-column-' + this.column_index + '=' + encodeURIComponent(subject));
    }
    window.location.href = parts.join('?');
}

/* only after everything is locked and loaded we’re initialising */
if ('complete' === document.readyState) {
    ruigehond014_compare_tables();
} else {
    window.addEventListener('load', function () {
        ruigehond014_compare_tables();
    });
}
