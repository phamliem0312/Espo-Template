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

define('advanced:views/report/reports/charts/grid2line', 'advanced:views/report/reports/charts/grid2bar-vertical', function (Dep) {

    return Dep.extend({

        columnWidth: 80,

        pointXHalfWidth: 0,

        isLine: true,

        getTickNumber: function () {
            var containerWidth = this.$container.width();
            var tickNumber = Math.floor(containerWidth / this.columnWidth);

            return tickNumber;
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

            var containerWidth = this.$container.width();
            var stripTicks = false;
            var tickNumber = this.getTickNumber();
            var pointCount = this.getDisplayedPointCount();

            var verticalLineNumber = pointCount;
            if (containerWidth / pointCount < this.columnWidth) {
                verticalLineNumber = tickNumber;
            } else {
                if (pointCount > tickNumber) {
                    stripTicks = true;
                    var tickDelta = Math.floor(pointCount / tickNumber);
                }
            }

            this.$graph = this.flotr.draw(this.$container.get(0), this.chartData, {
                shadowSize: false,
                colors: this.colorList,
                lines: {
                    show: true,
                    lineWidth: 3,
                },
                points: {
                    show: false,
                },
                grid: {
                    horizontalLines: true,
                    verticalLines: true,
                    outline: 'sw',
                    color: this.gridColor,
                    tickColor: this.tickColor
                },
                yaxis: {
                    min: 0,
                    max: this.max + this.max * 0.1,
                    min: this.min + this.min * 0.1,
                    showLabels: true,
                    autoscale: true,
                    autoscaleMargin: 1,
                    color: this.textColor,
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
                    noTicks: verticalLineNumber,
                    tickFormatter: function (value) {
                        if (value % 1 == 0) {
                            var i = parseInt(value);
                            if (stripTicks) {
                                if (i % tickDelta !== 0) return '';
                            }
                            if (i === 0) return '';
                            if (i in this.firstList) {
                                if (this.firstList.length > 4 && i === this.firstList.length - 1) {
                                    return '';
                                }
                                if (this.firstList.length > 200 && i > this.firstList.length - 4) {
                                    return '';
                                }
                                if (i === this.firstList.length - 1) {
                                    return '';
                                }
                                return this.formatGroup(0, this.firstList[i]);
                            }
                        }
                        return '';
                    }.bind(this)
                },
                mouse: {
                    track: true,
                    relative: true,
                    lineColor: this.hoverColor,
                    autoPositionHorizontal: true,
                    cursorPointer: true,
                    trackFormatter: function (obj) {
                        var i = Math.floor(obj.x);
                        var column = this.options.column;
                        return this.formatGroup(0, this.firstList[i])  + '<br>' + this.formatCellValue(obj.y, column);
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
                }
            });

            this.adjustLegend();

            if (this.dragStart) return;

            Flotr.EventAdapter.observe(this.$container.get(0), 'flotr:click', function (position) {
                if (!position.hit) return;
                if (!('index' in position.hit)) return;
                this.trigger('click-group', this.firstList[position.hit.index], null, this.secondList[position.hit.seriesIndex]);
            }.bind(this));
        }
    });
});
