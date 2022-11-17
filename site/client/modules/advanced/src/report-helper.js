/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

Espo.define('advanced:report-helper', ['view'], function (Fake) {

    var ReportHelper = function (metadata, language, dateTime, config, preferences) {
        this.metadata = metadata;
        this.language = language;
        this.dateTime = dateTime;
        this.config = config;
        this.preferences = preferences;

        var formatData = this.getFormatData();

        this.decimalMark = formatData.decimalMark;
        this.thousandSeparator = formatData.thousandSeparator;
        this.currencyDecimalPlaces = formatData.currencyDecimalPlaces;
        this.currencySymbol = formatData.currencySymbol;
        this.currency = formatData.currency;
        this.currencySymbol = formatData.currencySymbol;
        this.currencyFormat = formatData.currencyFormat;
    }

    _.extend(ReportHelper.prototype, {

        getFormatData: function () {
            var config = this.config;
            var preferences = this.preferences;

            var currency = config.get('defaultCurrency') || 'USD';
            var currencySymbol = this.getMetadata().get(['app', 'currency', 'symbolMap', currency]) || '';

            var decimalMark = '.';
            var thousandSeparator = ',';

            if (preferences.has('decimalMark')) {
                var decimalMark = preferences.get('decimalMark');
            } else {
                if (config.has('decimalMark')) {
                    var decimalMark = config.get('decimalMark');
                }
            }
            if (preferences.has('thousandSeparator')) {
                var thousandSeparator = preferences.get('thousandSeparator');
            } else {
                if (config.has('thousandSeparator')) {
                    var thousandSeparator = config.get('thousandSeparator');
                }
            }

            var currencyDecimalPlaces = config.get('currencyDecimalPlaces');

            return {
                currency: currency,
                currencySymbol: currencySymbol,
                decimalMark: decimalMark,
                thousandSeparator: thousandSeparator,
                currencyDecimalPlaces: currencyDecimalPlaces,
                currencyFormat: parseInt(config.get('currencyFormat'))
            };
        },

        formatCellValue: function (value, column, result, useSiMultiplier) {
            var arr = column.split(':');
            var isCurrency = false;

            var arr = column.split(':');
            if (arr.length === 1) {
                arr = ['', column];
            }

            if (arr.length > 1) {
                var data = this.getGroupFieldData(column, result) || {};

                var entityType = data.entityType;
                var field = data.field;
                var fieldType = data.fieldType;

                isCurrency = !!~['currency', 'currencyConverted'].indexOf(fieldType);
                if (!isCurrency && entityType === 'Opportunity' && field === 'amountWeightedConverted') {
                    isCurrency = true;
                }
            }

            return this.formatNumber(value, isCurrency, useSiMultiplier);
        },

        formatNumber: function (value, isCurrency, useSiMultiplier, noDecimalPart, no3CharCurrencyFormat) {
            var currencySymbol = this.currencySymbol;
            var decimalMark = this.decimalMark;
            var thousandSeparator = this.thousandSeparator;
            var currencyDecimalPlaces = this.currencyDecimalPlaces;

            var originalValue = value;

            var siSuffix = '';
            if (useSiMultiplier) {
                if (value >= 1000000) {
                    siSuffix = 'M';
                    value = value / 1000000;
                } else if (value >= 1000) {
                    siSuffix = 'k';
                    value = value / 1000;
                }
            }

            if (value !== null) {
                var maxDecimalPlaces = 2;

                if (isCurrency) {
                    if (!noDecimalPart && useSiMultiplier) {
                        if (siSuffix !== '') {
                            if (value >= 100) {
                                maxDecimalPlaces = 0;
                                currencyDecimalPlaces = 0;
                            } else if (value >= 10) {
                                maxDecimalPlaces = 1;
                                currencyDecimalPlaces = 1;
                            } else {
                                maxDecimalPlaces = 2;
                                currencyDecimalPlaces = 2;
                            }
                        }
                    }
                    if (noDecimalPart) {
                        currencyDecimalPlaces = null;
                    } else if (currencyDecimalPlaces === 0) {
                        value = Math.round(value);
                    } else if (currencyDecimalPlaces) {
                        value = Math.round(value * Math.pow(10, currencyDecimalPlaces)) / (Math.pow(10, currencyDecimalPlaces));
                    } else {
                        value = Math.round(value * Math.pow(10, maxDecimalPlaces)) / (Math.pow(10, maxDecimalPlaces));
                    }
                } else {
                    var maxDecimalPlaces = 4;
                    if (!noDecimalPart && useSiMultiplier) {
                        if (siSuffix !== '') {
                            if (value >= 10) {
                                maxDecimalPlaces = 1;
                            } else {
                                maxDecimalPlaces = 2;
                            }
                        }
                    }
                    value = Math.round(value * Math.pow(10, maxDecimalPlaces)) / (Math.pow(10, maxDecimalPlaces));
                }

                var parts = value.toString().split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

                if (isCurrency) {
                    if (currencyDecimalPlaces === 0) {
                        delete parts[1];
                    } else if (currencyDecimalPlaces) {
                        var decimalPartLength = 0;
                        if (parts.length > 1) {
                            decimalPartLength = parts[1].length;
                        } else {
                            parts[1] = '';
                        }

                        if (currencyDecimalPlaces && decimalPartLength < currencyDecimalPlaces) {
                            var limit = currencyDecimalPlaces - decimalPartLength;
                            for (var i = 0; i < limit; i++) {
                                parts[1] += '0';
                            }
                        }
                    }
                }

                var value = parts.join(decimalMark);
                if (isCurrency) {
                    if (this.currencyFormat === 1) {
                        if (no3CharCurrencyFormat) {
                            value = value + siSuffix;
                        } else {
                            value = value + siSuffix + ' ' + this.currency;
                        }
                    } else
                    if (this.currencyFormat === 3) {
                        value = value + siSuffix + ' ' + currencySymbol;
                    } else {
                        value = currencySymbol + value + siSuffix;
                    }
                } else {
                    value = value + siSuffix;
                }
                return value;
            }
            return '';
        },

        formatColumn: function (value, result) {
            var string = value;
            if (value in result.columnNameMap) {
                string = result.columnNameMap[value];
            }
            return Handlebars.Utils.escapeExpression(string);
        },

        formatGroup: function (gr, value, result) {
            var entityType = result.entityType;

            if (gr in result.groupValueMap) {
                var value = result.groupValueMap[gr][value] || value;
                if (value === '__STUB__') return '';
                if (value === null || value == '') {
                    value = this.language.translate('-Empty-', 'labels', 'Report');
                }
                return Handlebars.Utils.escapeExpression(value);
            }

            if (~gr.indexOf('MONTH:')) {
                return moment(value + '-01').format('MMM YYYY');
            } else if (~gr.indexOf('DAY:')) {

                var today = moment().tz(this.dateTime.getTimeZone()).startOf('day');
                var dateObj = moment(value);
                var readableFormat = this.dateTime.getReadableDateFormat();

                if (dateObj.format('YYYY') !== today.format('YYYY')) {
                    readableFormat += ', YYYY'
                }

                return dateObj.format(readableFormat);
            }

            if (value === null || value == '') {
                return this.language.translate('-Empty-', 'labels', 'Report');
            }
            return Handlebars.Utils.escapeExpression(value);
        },

        translateGroupName: function (item, entityType) {
            var hasFunction = false;
            var field = item;
            var fieldEntityType = entityType;

            var link = null
            var func = null;

            if (item == 'COUNT:id') {
                return this.language.translate('COUNT', 'functions', 'Report').toUpperCase();
            }

            var fieldData = this.getGroupFieldData(item, {entityType: entityType}) || {};

            var fieldEntityType = fieldData.entityType;
            var field = fieldData.field;
            var fieldType = fieldData.fieldType;
            var func = fieldData.function;
            var link = fieldData.link;

            var value = this.language.translate(field, 'fields', fieldEntityType);

            if (fieldType === 'currencyConverted' && field.substr(-9) === 'Converted') {
                value = this.language.translate(field.substr(0, field.length - 9), 'fields', fieldEntityType);
            }

            if (link) {
                value = this.language.translate(link, 'links', entityType) + '.' + value;
            }
            if (hasFunction) {
                value = this.language.translate(func, 'functions', 'Report').toUpperCase() + ': ' + value;
            }

            return value;
        },

        getCode: function () {
            return '4bc1026aa50a71b8840665043d28bcbc';
        },

        getMetadata: function () {
            return this.metadata;
        },

        getReportView: function (model) {
            var type = model.get('type');
            var groupBy = model.get('groupBy') || [];

            switch (type) {
                case 'Grid':
                case 'JointGrid':
                    var depth = model.get('depth') || groupBy.length;
                    if (depth > 2) {
                        throw new Error('Bad report.');
                    }
                    return 'advanced:views/report/reports/grid' + depth.toString();

                case 'List':
                    return 'advanced:views/report/reports/list';
            }

            throw new Error('Bad report type.');
        },

        getChartColumnGroupList: function (result) {
            var entityType = result.entityType;
            var columnList = result.numericColumnList || result.columns;

            var groupList = [];

            if (!~['Line', 'BarHorizontal', 'BarVertical'].indexOf(result.chartType)) {
                result.columns.forEach(function (column) {
                    groupList.push({column: column});
                });

                return groupList;
            }

            if (result.chartDataList && result.chartDataList.length && result.chartDataList[0]) {
                var columnList = (result.chartDataList[0].columnList || []).concat(
                    result.chartDataList[0].y2ColumnList || []
                );

                return [
                    {
                        columnList: columnList,
                        secondColumnList: result.chartDataList[0].y2ColumnList,
                        column: null,
                    }
                ];
            }

            if (!result.chartDataList && result.isJoint) {
                return [
                    {
                        columnList: columnList,
                        secondColumnList: [],
                    },
                ];
            }

            var sumCurrencyItemList = [];
            var currencyItemList = [];

            var secondColumn = null;
            var group1 = null;
            var group2 = null;

            var countColumnList = [];

            columnList.forEach(function (item) {
                var data = this.getGroupFieldData(item, result);

                if (!data) {
                    return;
                }

                if (!this.isColumnAggregated(item, result)) {
                    return;
                }

                var fieldType = data.fieldType;
                var field = data.field;
                var func = data.function;

                if (
                    data.fieldType === 'currencyConverted'
                    ||
                    (data.field == 'amountWeightedConverted' && data.entityType == 'Opportunity')
                ) {
                    if (
                        func === 'SUM'
                        ||
                        !func
                    ) {
                        sumCurrencyItemList.push(item);
                    } else {
                        currencyItemList.push(item);
                    }
                } else {
                    if (func === 'COUNT') {
                        countColumnList.push(item);
                    } else {
                        if (!secondColumn) {
                            secondColumn = item;
                        } else {
                            groupList.push({
                                column: item,
                            });
                        }
                    }
                }
            }, this);

            if (sumCurrencyItemList.length) {
                group1 = {
                    columnList: sumCurrencyItemList,
                };
            }
            if (currencyItemList.length) {
                group2 = {
                    columnList: currencyItemList,
                };
            }
            var group3 = null;

            if (secondColumn || countColumnList.length) {
                if (sumCurrencyItemList.length) {
                    if (countColumnList.length) {
                        group1.secondColumnList = countColumnList;
                        countColumnList.forEach(function (column) {
                            group1.columnList.push(column);
                        }, this);
                    } else {
                        group1.columnList.push(secondColumn);
                        group1.secondColumnList = [secondColumn];
                    }
                } else if (currencyItemList.length) {
                    if (countColumnList.length) {
                        group2.secondColumnList = countColumnList;
                        countColumnList.forEach(function (column) {
                            group2.columnList.push(column);
                        }, this);
                    } else {
                        group2.columnList.push(secondColumn);
                        group2.secondColumnList = [secondColumn];
                    }
                } else {
                    if (countColumnList.length > 1 || countColumnList.length && secondColumn) {
                        group3 = {
                            columnList: countColumnList
                        };
                        if (secondColumn) {
                            group3.columnList.push(secondColumn);
                            group3.secondColumnList = [secondColumn];
                        }
                    } else if (countColumnList.length === 1) {
                        group3 = {
                            column: countColumnList[0]
                        };
                    } else {
                        if (groupList.length) {
                            groupList[0].columnList = [secondColumn, groupList[0].column];
                            groupList[0].secondColumnList = [groupList[0].column];
                            groupList[0].column = null;
                        } else {
                            groupList.push({
                                column: secondColumn
                            })
                        }
                    }
                }
            }

            if (currencyItemList.length) {
                groupList.unshift(group2);
                if (currencyItemList.length === 1) {
                    group2.column = currencyItemList[0];
                    group2.columnList = null;
                }
            }
            if (sumCurrencyItemList.length) {
                groupList.unshift(group1);
                if (sumCurrencyItemList.length === 1) {
                    group1.column = sumCurrencyItemList[0];
                    group1.columnList = null;
                }
            }

            if (group3) {
                groupList.unshift(group3);
            }

            return groupList;
        },

        getGroupFieldData: function (item, result) {
            var entityType = result.entityType

            if (~item.indexOf('@')) {
                var arr = item.split('@');
                if (parseInt(arr[arr.length - 1]).toString() === arr[arr.length - 1]) {
                    var numString = arr[arr.length - 1];
                    var num = parseInt(numString);
                    item = item.substr(0, item.length - numString.length - 1);
                    entityType = result.entityTypeList[num];
                }
            }

            var field = item;
            var func = null;
            var link = null;

            if (~field.indexOf(':')) {
                field = item.split(':')[1];
                func = item.split(':')[0];
            }

            if (~item.indexOf('.')) {
                var arr = field.split('.');
                field = arr[1];
                var link = arr[0];
                entityType = this.metadata.get(['entityDefs', entityType, 'links', link, 'entity']);
                if (!entityType) return;
            }

            var fieldType = this.metadata.get(['entityDefs', entityType, 'fields', field, 'type']);

            return {
                entityType: entityType,
                field: field,
                fieldType: fieldType,
                function: func,
                link: link,
            };
        },

        isColumnNumeric: function (item, result) {
            if (typeof result === 'string') {
                result = {entityType: result};
            }
            var data = this.getGroupFieldData(item, result);

            if (result.numericColumnList && ~result.numericColumnList.indexOf(item)) return true;

            if (!!~['COUNT', 'SUM', 'AVG'].indexOf(data.function)) return true;

            return !!~['int', 'float', 'currencyConverted', 'currency', 'enumInt', 'enumFloat'].indexOf(data.fieldType);
        },

        isColumnAggregated: function (item, result) {
            if (!result.aggregatedColumnList) {
                return true;
            }

            return !!~result.aggregatedColumnList.indexOf(item);
        },

        isColumnSummary: function (item) {
            var isSummary = false;
            ['COUNT:', 'SUM:', 'AVG:', 'MIN:', 'MAX:'].forEach(function (part) {
                if (item.indexOf(part) === 0) {
                    isSummary = true;
                }
            }, this);
            return isSummary;
        },
    });

    return ReportHelper;
});
