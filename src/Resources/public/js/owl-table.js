/**
 * OwlTable - Client-side table controller
 * Vanilla JS, zero dependencies.
 */
(function () {
    'use strict';

    class OwlTable {
        constructor(wrapperEl) {
            this.wrapper = wrapperEl;
            this.tableId = wrapperEl.dataset.owlTableId;
            this.allData = JSON.parse(wrapperEl.dataset.owlTableData || '[]');
            this.columns = JSON.parse(wrapperEl.dataset.owlTableColumns || '[]');
            this.perPage = parseInt(wrapperEl.dataset.owlTablePerPage, 10) || 20;
            this.prefix = 'owl-table';

            this.currentPage = 1;
            this.sortField = null;
            this.sortDirection = 'asc';
            this.activeFilters = {};

            this.tableBody = wrapperEl.querySelector('tbody');
            this.paginationNav = wrapperEl.querySelector('[data-owl-table-pagination-for]')
                || document.querySelector('[data-owl-table-pagination-for="' + this.tableId + '"]');
            this.filtersContainer = document.querySelector(
                '[data-owl-table-filters-for="' + this.tableId + '"]'
            );

            this._bindSortButtons();
            this._bindFilterInputs();
            this.render();
        }

        // --- Data processing pipeline ---

        getProcessedData() {
            var data = this.allData.slice();
            data = this._applyFilters(data);
            data = this._applySorting(data);
            return data;
        }

        getPageData(processedData) {
            var start = (this.currentPage - 1) * this.perPage;
            return processedData.slice(start, start + this.perPage);
        }

        // --- Filtering ---

        _applyFilters(data) {
            var filters = this.activeFilters;
            var keys = Object.keys(filters);
            if (keys.length === 0) return data;

            var columns = this.columns;

            return data.filter(function (row) {
                for (var i = 0; i < keys.length; i++) {
                    var key = keys[i];
                    var filterVal = filters[key];

                    if (filterVal === '' || filterVal === null || filterVal === undefined) continue;

                    var col = null;
                    for (var j = 0; j < columns.length; j++) {
                        if (columns[j].key === key) {
                            col = columns[j];
                            break;
                        }
                    }
                    if (!col) continue;

                    var cellVal = (row[key] != null ? row[key] : '').toString();

                    switch (col.filterType) {
                        case 'text':
                            if (cellVal.toLowerCase().indexOf(filterVal.toLowerCase()) === -1) {
                                return false;
                            }
                            break;
                        case 'select':
                            if (cellVal !== filterVal) {
                                return false;
                            }
                            break;
                        case 'date_range':
                            if (filterVal && typeof filterVal === 'object') {
                                if (filterVal.from && cellVal < filterVal.from) return false;
                                if (filterVal.to && cellVal > filterVal.to) return false;
                            }
                            break;
                    }
                }
                return true;
            });
        }

        // --- Sorting ---

        _applySorting(data) {
            if (!this.sortField) return data;

            var field = this.sortField;
            var dir = this.sortDirection === 'desc' ? -1 : 1;

            return data.slice().sort(function (a, b) {
                var va = a[field] != null ? a[field] : '';
                var vb = b[field] != null ? b[field] : '';

                var na = parseFloat(va);
                var nb = parseFloat(vb);
                if (!isNaN(na) && !isNaN(nb)) {
                    return (na - nb) * dir;
                }

                va = va.toString().toLowerCase();
                vb = vb.toString().toLowerCase();
                if (va < vb) return -1 * dir;
                if (va > vb) return 1 * dir;
                return 0;
            });
        }

        // --- Rendering ---

        render() {
            var processed = this.getProcessedData();
            var totalItems = processed.length;
            var totalPages = Math.max(1, Math.ceil(totalItems / this.perPage));

            if (this.currentPage > totalPages) this.currentPage = totalPages;
            if (this.currentPage < 1) this.currentPage = 1;

            var pageData = this.getPageData(processed);

            this._renderBody(pageData);
            this._renderPagination(totalPages);
            this._updateSortIndicators();
        }

        _renderBody(rows) {
            var prefix = this.prefix;
            var columns = this.columns;
            var html = '';

            if (rows.length === 0) {
                html = '<tr class="' + prefix + '__row--empty">' +
                    '<td colspan="' + columns.length + '" class="' + prefix + '__td--empty">' +
                    'Aucun r\u00e9sultat.</td></tr>';
            } else {
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var parity = i % 2 === 0 ? 'odd' : 'even';
                    html += '<tr class="' + prefix + '__row ' + prefix + '__row--' + parity + '">';
                    for (var j = 0; j < columns.length; j++) {
                        var col = columns[j];
                        var val = this._escapeHtml((row[col.key] != null ? row[col.key] : '').toString());
                        html += '<td class="' + prefix + '__td" data-label="' +
                            this._escapeHtml(col.label) + '" data-column-key="' +
                            col.key + '">' + val + '</td>';
                    }
                    html += '</tr>';
                }
            }

            this.tableBody.innerHTML = html;
        }

        _renderPagination(totalPages) {
            if (!this.paginationNav) return;

            if (totalPages <= 1) {
                this.paginationNav.innerHTML = '';
                return;
            }

            var prefix = this.prefix;
            var current = this.currentPage;
            var pages = this._buildPageRange(current, totalPages, 2);
            var html = '<ul class="' + prefix + '-pagination__list">';

            // Prev
            var prevDisabled = current <= 1;
            html += '<li class="' + prefix + '-pagination__item' +
                (prevDisabled ? ' ' + prefix + '-pagination__item--disabled' : '') + '">';
            html += '<button type="button" class="' + prefix + '-pagination__link ' +
                prefix + '-pagination__link--prev" data-page="' + (current - 1) + '"' +
                (prevDisabled ? ' disabled' : '') + '>&laquo; Pr\u00e9c\u00e9dent</button></li>';

            // Pages
            for (var i = 0; i < pages.length; i++) {
                var p = pages[i];
                if (p === null) {
                    html += '<li class="' + prefix + '-pagination__item ' +
                        prefix + '-pagination__item--ellipsis"><span class="' +
                        prefix + '-pagination__ellipsis">&hellip;</span></li>';
                } else {
                    var active = p === current;
                    html += '<li class="' + prefix + '-pagination__item' +
                        (active ? ' ' + prefix + '-pagination__item--active' : '') + '">';
                    html += '<button type="button" class="' + prefix + '-pagination__link" data-page="' +
                        p + '"' + (active ? ' aria-current="page"' : '') + '>' + p + '</button></li>';
                }
            }

            // Next
            var nextDisabled = current >= totalPages;
            html += '<li class="' + prefix + '-pagination__item' +
                (nextDisabled ? ' ' + prefix + '-pagination__item--disabled' : '') + '">';
            html += '<button type="button" class="' + prefix + '-pagination__link ' +
                prefix + '-pagination__link--next" data-page="' + (current + 1) + '"' +
                (nextDisabled ? ' disabled' : '') + '>Suivant &raquo;</button></li>';

            html += '</ul>';
            this.paginationNav.innerHTML = html;

            this._bindPaginationButtons();
        }

        _updateSortIndicators() {
            var prefix = this.prefix;
            var ths = this.wrapper.querySelectorAll('th[data-sort-key]');
            for (var i = 0; i < ths.length; i++) {
                var th = ths[i];
                th.classList.remove(prefix + '__th--sorted', prefix + '__th--asc', prefix + '__th--desc');
                if (th.dataset.sortKey === this.sortField) {
                    th.classList.add(prefix + '__th--sorted', prefix + '__th--' + this.sortDirection);
                }
            }
        }

        // --- Event binding ---

        _bindSortButtons() {
            var self = this;
            var buttons = this.wrapper.querySelectorAll('button[data-sort-key]');
            for (var i = 0; i < buttons.length; i++) {
                (function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        var key = btn.dataset.sortKey;
                        if (self.sortField === key) {
                            self.sortDirection = self.sortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            self.sortField = key;
                            self.sortDirection = 'asc';
                        }
                        self.currentPage = 1;
                        self.render();
                    });
                })(buttons[i]);
            }
        }

        _bindFilterInputs() {
            if (!this.filtersContainer) return;

            var self = this;

            // Text inputs with debounce
            var textInputs = this.filtersContainer.querySelectorAll('input[type="text"]');
            for (var i = 0; i < textInputs.length; i++) {
                (function (input) {
                    var timer;
                    input.addEventListener('input', function () {
                        clearTimeout(timer);
                        timer = setTimeout(function () {
                            var key = self._extractFilterKey(input.name);
                            self.activeFilters[key] = input.value;
                            self.currentPage = 1;
                            self.render();
                        }, 300);
                    });
                })(textInputs[i]);
            }

            // Select inputs
            var selects = this.filtersContainer.querySelectorAll('select');
            for (var i = 0; i < selects.length; i++) {
                (function (select) {
                    select.addEventListener('change', function () {
                        var key = self._extractFilterKey(select.name);
                        self.activeFilters[key] = select.value;
                        self.currentPage = 1;
                        self.render();
                    });
                })(selects[i]);
            }

            // Date range inputs
            var dateInputs = this.filtersContainer.querySelectorAll('input[type="date"]');
            for (var i = 0; i < dateInputs.length; i++) {
                (function (dateInput) {
                    dateInput.addEventListener('change', function () {
                        var nameParts = dateInput.name.match(/filter\[(\w+)\]\[(\w+)\]/);
                        if (nameParts) {
                            var key = nameParts[1];
                            var bound = nameParts[2];
                            if (!self.activeFilters[key] || typeof self.activeFilters[key] !== 'object') {
                                self.activeFilters[key] = {};
                            }
                            self.activeFilters[key][bound] = dateInput.value;
                            self.currentPage = 1;
                            self.render();
                        }
                    });
                })(dateInputs[i]);
            }

            // Prevent form submission in client mode
            var form = this.filtersContainer.querySelector('form');
            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                });
            }
        }

        _bindPaginationButtons() {
            var self = this;
            if (!this.paginationNav) return;

            var buttons = this.paginationNav.querySelectorAll('button[data-page]');
            for (var i = 0; i < buttons.length; i++) {
                (function (btn) {
                    btn.addEventListener('click', function () {
                        var page = parseInt(btn.dataset.page, 10);
                        if (page >= 1) {
                            self.currentPage = page;
                            self.render();
                        }
                    });
                })(buttons[i]);
            }
        }

        // --- Utilities ---

        _extractFilterKey(name) {
            var match = name.match(/filter\[(\w+)\]/);
            return match ? match[1] : name;
        }

        _buildPageRange(current, total, delta) {
            var range = [1];
            var rangeStart = Math.max(2, current - delta);
            var rangeEnd = Math.min(total - 1, current + delta);

            if (rangeStart > 2) range.push(null);
            for (var i = rangeStart; i <= rangeEnd; i++) range.push(i);
            if (rangeEnd < total - 1) range.push(null);
            if (total > 1) range.push(total);

            return range;
        }

        _escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    }

    // --- Auto-initialization ---

    function init() {
        var wrappers = document.querySelectorAll('[data-owl-table-mode="client"]');
        for (var i = 0; i < wrappers.length; i++) {
            if (!wrappers[i]._owlTableInstance) {
                wrappers[i]._owlTableInstance = new OwlTable(wrappers[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.OwlTable = { init: init, Controller: OwlTable };

})();
