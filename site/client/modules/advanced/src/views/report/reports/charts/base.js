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

define('advanced:views/report/reports/charts/base', ['view', 'lib!Flotr'], function (Dep, Flotr) {

    return Dep.extend({

        template: 'advanced:report/reports/charts/chart',

        decimalMark: '.',

        thousandSeparator: ',',

        colorList: ['#6FA8D6', '#4E6CAD', '#EDC555', '#ED8F42', '#DE6666', '#7CC4A4', '#8A7CC2', '#D4729B'],

        colorListAlt: ['#6FA8D6', '#EDC555', '#ED8F42', '#7CC4A4', '#D4729B'],

        successColor: '#5ABD37',

        gridColor: '#ddd',

        tickColor: '#e8eced',

        textColor: '#333',

        hoverColor: '#FF3F19',

        defaultHeight: 350,

        legendColumnWidth: 110,

        legendColumnNumber: 8,

        noLegend: false,

        zoomMaxDistanceBetweenPoints: 60,

        zoomStepRatio: 1.5,

        pointXHalfWidth: 0,

        zoomMaxDistanceMultiplier: 1,

        init: function () {
            Dep.prototype.init.call(this);

            this.flotr = this.Flotr = Flotr;

            this.reportHelper = this.options.reportHelper;

            this.successColor = this.getThemeManager().getParam('chartSuccessColor') || this.successColor;
            this.colorList = this.getThemeManager().getParam('chartColorList') || this.colorList;
            this.colorListAlt = this.getThemeManager().getParam('chartColorAlternativeList') || this.colorListAlt;
            this.gridColor = this.getThemeManager().getParam('chartGridColor') || this.gridColor;
            this.tickColor = this.getThemeManager().getParam('chartTickColor') || this.tickColor;
            this.textColor = this.getThemeManager().getParam('textColor') || this.textColor;
            this.hoverColor = this.getThemeManager().getParam('hoverColor') || this.hoverColor;

            this.defaultHeight = this.options.defaultHeight || this.defaultHeight;

            if (this.options.colorList && this.options.colorList.length) {
                this.colorList = this.options.colorList;
                this.colorListAlt = this.options.colorList;
            }

            this.colors = this.options.colors || {};

            this.on('resize', function () {
                if (!this.isRendered()) return;
                setTimeout(function () {
                    this.adjustContainer();
                    this.processDraw();
                }.bind(this), 50);
            }, this);

            $(window).on('resize.report-chart-'+this.cid, function () {
                this.adjustContainer();
                this.processDraw();
            }.bind(this));

            this.listenToOnce(this, 'remove', function () {
                $(window).off('resize.report-chart-'+this.cid);

                if (this.zooming && !this.options.isDashletMode) {
                    $(document).off('mouseup.' + this.cid);
                    $(document).off('touchend.' + this.cid);
                    if (this.$container.get(0)) {
                        Flotr.EventAdapter.stopObserving(this.$container.get(0), 'mousemove');
                        Flotr.EventAdapter.stopObserving(this.$container.get(0), 'touchmove');
                    }
                }
                if (this.$graph) {
                    this.$graph.destroy();
                }
            }, this);

            this.result = this.options.result;
            this.column = this.options.column;
            this.columnList = this.options.columnList;
            this.secondColumnList = this.options.secondColumnList;

            var firstColumn = this.column;
            if (this.columnList && this.columnList.length) firstColumn = this.columnList[0];
            if (this.result.columnTypeMap && this.result.columnTypeMap[firstColumn]) {
                this.isCurrency = this.result.columnTypeMap[firstColumn] === 'currencyConverted';
            }

            if (this.zooming && !this.options.isDashletMode) {
                this.events = this.events || {};
                this.events['click [data-action="zoomIn"]'] = this.zoomIn;
                this.events['click [data-action="zoomOut"]'] = this.zoomOut;
            }
        },

        labelFormatter: function (v) {
            return '<span style="color:'+this.textColor+'">' + v + '</span>';
        },

        formatCellValue: function (value, column) {
            return this.reportHelper.formatCellValue(value, column, this.result);
        },

        formatNumber: function (value, isCurrency, useSiMultiplier, noDecimalPart, no3CharCurrencyFormat) {
            return this.reportHelper.formatNumber(value, isCurrency, useSiMultiplier, noDecimalPart, no3CharCurrencyFormat);
        },

        adjustContainer: function () {
            var heightCss;

            if (this.options.fitHeight) {
                var legendHeight = 0;
                var substract = 0;
                if (!this.noLegend) {
                    substract += this.getLegendHeight();
                }
                if (this.options.heightSubstract) {
                    substract += this.options.heightSubstract;
                }
                if (substract) {
                    heightCss = 'calc(100% - '+substract.toString()+'px)';
                } else {
                    heightCss = this.options.height || (this.defaultHeight + 'px');
                }
            } else {
                var heightCalculated;
                if (!this.options.height) {
                    heightCalculated = this.calculateHeight();
                    if (this.defaultHeight) {
                        if (heightCalculated < this.defaultHeight) {
                            heightCalculated = null;
                        }
                    }
                }
                if (heightCalculated) {
                    heightCss = heightCalculated + 'px';
                } else {
                    heightCss = this.options.height || (this.defaultHeight + 'px');
                }
            }
            this.$container.css('height', heightCss);
        },

        beforeDraw: function () {
            if (this.zooming && !this.options.isDashletMode) {
                if (this.$container.get(0)) {
                    Flotr.EventAdapter.stopObserving(this.$container.get(0), 'mousemove');
                }
            }
        },

        afterDraw: function () {
            if (this.zooming && !this.options.isDashletMode) this.controlZoomButtons();

            if (this.zooming && !this.dragStart) {
                Flotr.EventAdapter.stopObserving(this.$container.get(0), 'flotr:mousedown');
                Flotr.EventAdapter.stopObserving(this.$container.get(0), 'touchstart');
                if (this.isZoomed) {
                    Flotr.EventAdapter.observe(this.$container.get(0), 'flotr:mousedown', this.initDrag.bind(this));
                    Flotr.EventAdapter.observe(this.$container.get(0), 'touchstart', this.initTouchDrag.bind(this));
                }
            }

            if (this.zooming && !this.options.isDashletMode && this.isZoomed) {
                this.$el.css('overflow', 'hidden');
            }
        },

        getDisplayedPointCount: function () {
            var pointCount;
            if (this.xMax) {
                pointCount = this.xMax - this.xMin;
            } else {
                pointCount = this.getHorizontalPointCount();
            }
            pointCount = Math.round(pointCount);
            return pointCount;
        },

        controlZoomButtons: function () {
            if (this.$zoomIn) this.$zoomIn.remove();
            var rightOffset = 0;

            if (this.secondColumnList) rightOffset += 30;

            this.$zoomIn = $('<a href="javascript:" data-action="zoomIn"><span class="fas fa-plus fa-sm"></span></a>');
            this.$zoomIn.css('position', 'absolute');
            this.$zoomIn.css('right', rightOffset + 0);
            this.$zoomIn.css('top', 0);

            this.$zoomOut = $('<a href="javascript:" data-action="zoomOut"><span class="fas fa-minus fa-sm"></span></a>');
            this.$zoomOut.css('position', 'absolute');
            this.$zoomOut.css('right', rightOffset + 20);
            this.$zoomOut.css('top', 0);

            if (!this.zoomRatio || this.zoomRatio === 1.0) {
                this.$zoomOut.css('display', 'none');
            }

            var pointCount = this.getDisplayedPointCount();

            if (pointCount <= 1 || this.$container.width() / pointCount > (this.zoomMaxDistanceBetweenPoints * this.zoomMaxDistanceMultiplier)) {
                this.$zoomIn.css('display', 'none');
            }

            this.$container.append(this.$zoomIn);
            this.$container.append(this.$zoomOut);
        },

        zoomIn: function () {
            if (this.xMin === undefined) this.xMin = 0 - this.pointXHalfWidth;
            if (this.xMax === undefined) this.xMax = this.getHorizontalPointCount() + this.pointXHalfWidth;

            var diff = this.xMax - this.xMin;
            if (diff <= 1) return;

            this.middle = diff / 2;
            var newDiff = diff / this.zoomStepRatio;

            var pointCount = this.getHorizontalPointCount();

            this.xMin = Math.ceil(this.middle - newDiff / 2);
            this.xMax = Math.floor(this.middle + newDiff / 2);
            this.zoomRatio = pointCount / (this.xMax - this.xMin);

            this.isZoomed = true;

            this.processDraw();
        },

        zoomOut: function () {
            if (this.xMin === undefined) this.xMin = 0 - this.pointXHalfWidth;
            if (this.xMax === undefined) this.xMax = this.getHorizontalPointCount();

            var diff = this.xMax - this.xMin;

            this.middle = diff / 2;
            var newDiff = Math.round(diff * this.zoomStepRatio, 2);

            this.xMin = Math.floor(this.xMin - newDiff / 2);
            this.xMax = Math.ceil(this.xMax + newDiff / 2);

            var pointCount = this.getHorizontalPointCount();

            if (this.xMin < 0 - this.pointXHalfWidth) this.xMin = 0 - this.pointXHalfWidth;
            if (this.xMax > pointCount) this.xMax = pointCount;

            this.zoomRatio = pointCount / (this.xMax - this.xMin - this.pointXHalfWidth);

            if (this.zoomRatio === 1.0) {
                this.isZoomed = false;
            }

            this.processDraw();
        },

        processDraw: function () {
            this.beforeDraw();
            this.draw();
            this.afterDraw();
        },

        getHorizontalPointCount: function () {},

        initDrag: function (e) {
            this.dragStart = this.$graph.getEventPosition(e);

            Flotr.EventAdapter.observe(this.$container.get(0), 'mousemove', this.drag.bind(this));

            $(document).off('mouseup.' + this.cid);
            $(document).on('mouseup.' + this.cid, this.stopDrag.bind(this));

            this.$container.css('cursor', 'grabbing');
        },

        initTouchDrag: function (e) {
            this.dragStart = {
                isTouch: true,
                x: this.$graph.axes.x.p2d(e.touches[0].clientX - this.$container.get(0).getBoundingClientRect().left)
            };

            Flotr.EventAdapter.observe(this.$container.get(0), 'touchmove', this.drag.bind(this));

            $(document).off('touchend.' + this.cid);
            $(document).on('touchend.' + this.cid, this.stopTouchDrag.bind(this));
        },

        stopDrag: function () {
            $(document).off('mouseup.' + this.cid);
            Flotr.EventAdapter.stopObserving(this.$container.get(0), 'mousemove');
            this.dragStart = null;

            this.$container.css('cursor', '');

            setTimeout(function () {
                this.processDraw();
            }.bind(this), 50);
        },

        stopTouchDrag: function () {
            $(document).off('touchend.' + this.cid);
            Flotr.EventAdapter.stopObserving(this.$container.get(0), 'touchmove');
            this.dragStart = null;

            setTimeout(function () {
                this.processDraw();
            }.bind(this), 50);
        },

        drag: function (e) {
            if (!this.dragStart) return;

            var offset;

            if (this.dragStart.isTouch) {
                var x = e.changedTouches[0].clientX - this.$container.get(0).getBoundingClientRect().left;
                offset = this.dragStart.x - this.$graph.axes.x.p2d(x);
            } else {
                var end = this.$graph.getEventPosition(e);
                offset = this.dragStart.x - end.x;
            }

            var pointCount = this.getHorizontalPointCount() - 1;

            var xMin = this.xMin;
            var xMax = this.xMax;

            this.xMin = this.xMin + offset;
            this.xMax = this.xMax + offset;

            if (this.xMin < 0 - this.pointXHalfWidth) {
                this.xMax = xMax + offset - (this.xMin + this.pointXHalfWidth);
                this.xMin = 0 - this.pointXHalfWidth;
            } else if (this.xMax > pointCount + this.pointXHalfWidth) {
                this.xMin = xMin + offset - (this.xMax - pointCount - this.pointXHalfWidth);
                this.xMax = pointCount + this.pointXHalfWidth;
            }
            this.draw(true);
        },

        calculateHeight: function () {
            return null;
        },

        adjustLegend: function () {
            var number = this.getLegendColumnNumber();
            if (!number) return;

            var dashletChartLegendBoxWidth = this.getThemeManager().getParam('dashletChartLegendBoxWidth') || 21;

            var containerWidth = this.$legendContainer.width();

            var width = Math.floor((containerWidth - dashletChartLegendBoxWidth * number) / number);

            var columnNumber = this.$legendContainer.find('> table tr:first-child > td').length / 2;
            var tableWidth = (width + dashletChartLegendBoxWidth) * columnNumber;

            this.$legendContainer.find('> table')
                .css('table-layout', 'fixed')
                .attr('width', tableWidth);
            this.$legendContainer.find('td.flotr-legend-label').attr('width', width);
            this.$legendContainer.find('td.flotr-legend-color-box').attr('width', dashletChartLegendBoxWidth);

            this.$legendContainer.find('td.flotr-legend-label > span').each(function(i, span) {
                span.setAttribute('title', span.textContent);
            }.bind(this));
        },

        afterRender: function () {
            this.prepareData();

            this.$container = this.$el.find('.chart-container');
            this.$legendContainer = this.$el.find('.legend-container');

            this.adjustContainer();

            setTimeout(function () {
                this.processDraw();
            }.bind(this), 1);
        },

        getLegendColumnNumber: function () {
            var width = this.getParentView().$el.width();
            var legendColumnNumber = Math.floor(width / this.legendColumnWidth);
            return legendColumnNumber || this.legendColumnNumber;
        },

        getLegendHeight: function () {
            if (this.noLegend) {
                return 0;
            }
            var lineNumber = Math.ceil(this.chartData.length / this.getLegendColumnNumber());
            var legendHeight = 0;

            var lineHeight = this.getThemeManager().getParam('dashletChartLegendRowHeight') || 19;
            var paddingTopHeight = this.getThemeManager().getParam('dashletChartLegendPaddingTopHeight') || 7;

            if (lineNumber > 0) {
                legendHeight = lineHeight * lineNumber + paddingTopHeight;
            }

            return legendHeight;
        },

        showNoData: function () {
            var fontSize = this.getThemeManager().getParam('fontSize') || 14;
            this.$container.empty();
            var textFontSize = fontSize * 1.2;

            var $text = $('<span>').html(this.translate('No Data')).addClass('text-muted');

            var $div = $('<div>').css('text-align', 'center')
                                 .css('font-size', textFontSize + 'px')
                                 .css('display', 'table')
                                 .css('width', '100%')
                                 .css('height', '100%');

            $text
                .css('display', 'table-cell')
                .css('vertical-align', 'middle')
                .css('padding-bottom', fontSize * 1.5 + 'px');


            $div.append($text);

            this.$container.append($div);
        }

    });
});
