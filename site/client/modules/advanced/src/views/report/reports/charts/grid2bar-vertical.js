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

define('advanced:views/report/reports/charts/grid2bar-vertical', 'advanced:views/report/reports/charts/base', function (Dep) {

    return Dep.extend({

        columnWidth: 60,

        barWidth: 0.5,

        zooming: true,

        pointXHalfWidth: 0.5,

        prepareData: function () {
            var result = this.result;

            var firstList = this.firstList = result.grouping[0];
            var secondList = this.secondList = result.grouping[1];

            if (secondList.length > 5) {
                this.colorList = this.colorList;
            } else {
                this.colorList = this.colorListAlt;
            }

            var columns = [];

            var i = 0;

            this.sumList = [];

            this.max = 0;

            this.min = 0;

            firstList.forEach(function (gr1) {
                var columnData = {};
                var sum = 0;

                secondList.forEach(function (gr2) {
                    if (result.reportData[gr1] && result.reportData[gr1][gr2]) {
                        var value = result.reportData[gr1][gr2][this.column] || 0;
                        columnData[gr2] = value;

                        if (value > this.max) {
                            this.max = value;
                        }


                        if (value < this.min) {
                            this.min = value;
                        }
                    }
                }, this);
                columns.push(columnData);

                sum = (result.group1Sums[gr1] || {})[this.column] || 0;
                this.sumList.push(sum);

                i++;
            }, this);

            var dataByGroup2 = {};

            var group2Count = this.group2Count = secondList.length;

            if (this.isGrouped && group2Count) {
                this.barWidth = 1 / (group2Count) * 0.65;
            }

            var baseShift = 1 / group2Count;
            var middleIndex = Math.ceil(group2Count / 2) - 1;

            secondList.forEach(function (gr2, j) {
                var shift = 0;
                if (this.isGrouped) {
                    var diffIndex = j - middleIndex;
                    shift = baseShift * diffIndex;

                    if (group2Count % 2 === 0) {
                        shift -= baseShift / 2;
                    }

                    shift *= 0.75;
                }

                dataByGroup2[gr2] = [];

                columns.forEach(function (columnData, i) {
                    dataByGroup2[gr2].push([i + shift, columnData[gr2] || 0]);
                }, this);
            }, this);

            var data = [];

            secondList.forEach(function (gr2, i) {
                var o = {
                    data: dataByGroup2[gr2],
                    label: this.formatGroup(1, gr2)
                };

                if (this.result.success && this.result.success == gr2) {
                    o.color = this.successColor;
                }

                if (gr2 in this.colors) {
                    o.color = this.colors[gr2];
                }

                data.push(o);
            }, this);

            if (!this.isGrouped && !this.isLine) {
                this.max = 0;
                if (this.sumList.length) {
                    this.max = this.sumList.reduce(function(a, b) {
                        return Math.max(a, b);
                    });
                }
            }

            if (this.isGrouped && !this.isLine) {
                this.zoomMaxDistanceMultiplier = 1;

                if (this.sumList.length) {
                    this.zoomMaxDistanceMultiplier = this.sumList.length;

                    if (this.sumList.length > 10) {
                        this.zoomMaxDistanceMultiplier = 4;
                    } else if (this.sumList.length > 3) {
                        this.zoomMaxDistanceMultiplier = 2;
                    }
                }
            }

            this.chartData = data;

            var version = this.getConfig().get('version') || '';
            var arr = version.split('.');

            if (version !== 'dev' && arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) * 10 +  parseInt(arr[2]) < 562) {
                this.noMouseTrack = true;
            }
        },

        formatGroup: function (i, value) {
            var gr = this.result.groupBy[i];
            if (this.result.groupBy.length === 0) {
                gr = '__STUB__';
            }
            return this.reportHelper.formatGroup(gr, value, this.result);
        },

        getTickNumber: function () {
            var containerWidth = this.$container.width();
            var tickNumber = Math.floor(containerWidth / this.columnWidth);
            return tickNumber;
        },

        getHorizontalPointCount: function () {
            return this.sumList.length;
        },

        isNoData: function () {
            return !this.chartData.length;
        },

        draw: function () {
            if (this.$container.height() === 0) {
                this.$container.empty();

                return;
            }

            if (this.isNoData()) {
                this.showNoData();

                return;
            }

            var tickNumber = this.getTickNumber();

            this.$graph = this.flotr.draw(this.$container.get(0), this.chartData, {
                shadowSize: false,
                colors: this.colorList,
                bars: {
                    show: true,
                    stacked : !this.isGrouped,
                    horizontal: false,
                    shadowSize: 0,
                    lineWidth: 1,
                    fillOpacity: 1,
                    barWidth: this.barWidth,
                },
                grid: {
                    horizontalLines: true,
                    verticalLines: false,
                    outline: 'sw',
                    color: this.gridColor,
                    tickColor: this.tickColor
                },
                yaxis: {
                    min: 0,
                    showLabels: true,
                    color: this.textColor,
                    max: this.max + this.max * 0.1,
                    min: this.min + this.min * 0.1,
                    tickFormatter: function (value) {
                        if (value == 0 && this.min === 0) {
                            return '';
                        }

                        if (value > this.max + 0.09 * this.max) {
                            return '';
                        }

                        if (value % 1 == 0) {
                            return this.formatNumber(Math.floor(value), this.isCurrency, true, true, true).toString();
                        }
                        return '';
                    }.bind(this)
                },
                xaxis: {
                    min: this.xMin || 0,
                    max: this.xMax || null,
                    color: this.textColor,
                    noTicks: tickNumber,
                    tickFormatter: function (value) {
                        if (value % 1 == 0) {
                            var i = parseInt(value);
                            if (i in this.firstList) {
                                if (this.firstList.length - tickNumber > 5 && i === this.firstList.length - 1) {
                                    return '';
                                }
                                return this.formatGroup(0, this.firstList[i]);
                            }
                        }
                        return '';
                    }.bind(this)
                },
                legend: {
                    show: true,
                    noColumns: this.getLegendColumnNumber(),
                    container: this.$el.find('.legend-container'),
                    labelBoxMargin: 0,
                    labelFormatter: this.labelFormatter.bind(this),
                    labelBoxBorderColor: 'transparent',
                    backgroundOpacity: 0
                },
                mouse: {
                    track: this.isGrouped || !this.noMouseTrack,
                    relative: true,
                    position: 's',
                    lineColor: this.hoverColor,
                    autoPositionVertical: this.isGrouped,
                    autoPositionHorizontalHalf: !this.isGrouped,
                    cursorPointer: this.isGrouped || !this.noMouseTrack,
                    trackFormatter: function (obj, e) {
                        var i = Math.round(obj.x);
                        var column = this.options.column;
                        var value = obj.series.data[obj.index][1];

                        return this.formatGroup(0, this.firstList[i]) + '<br>' + obj.series.label +
                            '<br>' + this.formatCellValue(value, column);
                    }.bind(this)
                },
            });

            this.adjustLegend();

            if (this.dragStart) {
                return;
            }

            if (this.isGrouped || !this.noMouseTrack) {
                Flotr.EventAdapter.observe(this.$container.get(0), 'flotr:click', function (position) {
                    if (!position.hit) {
                        return;
                    }

                    if (!('index' in position.hit)) {
                        return;
                    }

                    this.trigger(
                        'click-group',
                        this.firstList[position.hit.index],
                        null,
                        this.secondList[position.hit.seriesIndex]
                    );
                }.bind(this));
            }
        }
    });
});
