<?php
/**
 * Plugin Name: Mortgage Rate Comparison
 * Description: Compare current mortgage rates and total costs over a deal period against a new rate to see potential savings.
 * Version: 1.2.1
 * Author: Codex Test
 * Text Domain: mortgage-rate-compare
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

const MRC_HANDLE = 'mrc-comparison';

/**
 * Register script and style assets.
 *
 * Assets are inline for portability so the widget and shortcode can be styled within Elementor.
 */
function mrc_register_assets() {
    wp_register_script(MRC_HANDLE, '', [], '1.1.0', true);
    wp_register_style(MRC_HANDLE, '', [], '1.1.0');

    $script = <<<'JS'
    (() => {
        function currency(value) {
            return value.toLocaleString(undefined, { style: 'currency', currency: 'GBP' });
        }

        function monthlyPayment(balance, annualRate, termYears) {
            const monthlyRate = (annualRate / 100) / 12;
            const months = termYears * 12;
            if (monthlyRate === 0) {
                return balance / months;
            }
            const factor = Math.pow(1 + monthlyRate, months);
            return balance * (monthlyRate * factor) / (factor - 1);
        }

        function totalCost(balance, annualRate, termYears, dealYears, fees) {
            const payment = monthlyPayment(balance, annualRate, termYears);
            const monthsToCompare = Math.min(termYears, dealYears) * 12;
            return {
                payment,
                total: payment * monthsToCompare + (fees || 0),
                months: monthsToCompare
            };
        }

        function showError(container, message) {
            const error = container.querySelector('.mrc-error');
            if (!error) return;
            error.textContent = message;
            error.hidden = false;
            const results = container.querySelector('.mrc-results');
            if (results) {
                results.hidden = true;
            }
        }

        function clearError(container) {
            const error = container.querySelector('.mrc-error');
            if (!error) return;
            error.textContent = '';
            error.hidden = true;
        }

        function attachHandlers() {
            document.querySelectorAll('.mrc-comparison-form').forEach(form => {
                if (form.dataset.mrcBound === 'true') {
                    return;
                }

                form.dataset.mrcBound = 'true';
                form.addEventListener('submit', event => {
                    event.preventDefault();
                    const container = form.closest('.mrc-comparison');
                    if (!container) return;

                    const balance = parseFloat(form.querySelector('[data-field="balance"]').value);
                    const termYears = parseFloat(form.querySelector('[data-field="term-years"]').value);
                    const dealYears = parseFloat(form.querySelector('[data-field="deal-years"]').value);
                    const currentRate = parseFloat(form.querySelector('[data-field="current-rate"]').value);
                    const newRate = parseFloat(form.querySelector('[data-field="new-rate"]').value);
                    const fees = parseFloat(form.querySelector('[data-field="fees"]').value) || 0;

                    if ([balance, termYears, dealYears, currentRate, newRate].some(Number.isNaN)) {
                        showError(container, 'Please complete all required fields before calculating.');
                        return;
                    }

                    if (dealYears <= 0 || termYears <= 0 || balance <= 0) {
                        showError(container, 'Balance, remaining term, and deal period must all be greater than zero.');
                        return;
                    }

                    clearError(container);

                    const current = totalCost(balance, currentRate, termYears, dealYears, 0);
                    const next = totalCost(balance, newRate, termYears, dealYears, fees);
                    const difference = current.total - next.total;

                    const summary = container.querySelector('.mrc-summary');
                    const breakdown = container.querySelector('.mrc-breakdown');
                    const results = container.querySelector('.mrc-results');

                    if (!summary || !breakdown || !results) {
                        return;
                    }

                    summary.innerHTML = difference > 0
                        ? `Switching saves you ${currency(difference)} over ${dealYears} years.`
                        : difference < 0
                            ? `Switching costs you ${currency(Math.abs(difference))} more over ${dealYears} years.`
                            : 'Both options cost the same over the chosen period.';

                    breakdown.innerHTML = '';

                    const items = [
                        `Current monthly payment: ${currency(current.payment)} for ${current.months} months`,
                        `Current total cost over ${dealYears} years: ${currency(current.total)}`,
                        `New monthly payment: ${currency(next.payment)} for ${next.months} months`,
                        `New total cost over ${dealYears} years (including fees): ${currency(next.total)}`
                    ];

                    items.forEach(text => {
                        const li = document.createElement('li');
                        li.textContent = text;
                        breakdown.appendChild(li);
                    });

                    results.hidden = false;
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', attachHandlers);
        } else {
            attachHandlers();
        }
    })();
    JS;

    $style = <<<'CSS'
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

    .mrc-comparison {
        font-family: 'Poppins', sans-serif;
        max-width: 460px;
        width: 100%;
        background: radial-gradient(circle at 0 0, rgba(255, 255, 255, 0.04), transparent 35%),
            radial-gradient(circle at 100% 0, rgba(255, 255, 255, 0.04), transparent 35%),
            #2e3138;
        color: #f5f7fb;
        padding: 28px 32px;
        border-radius: 22px;
        box-shadow: 0 18px 50px rgba(0, 0, 0, 0.35);
    }

    .mrc-comparison .mrc-heading {
        margin: 0 0 12px;
        font-size: 22px;
        font-weight: 700;
        color: #ffffff;
        line-height: 1.3;
    }

    .mrc-comparison .mrc-field {
        margin-bottom: 14px;
    }

    .mrc-comparison .mrc-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #c9ced8;
        font-size: 13px;
        letter-spacing: 0.02em;
    }

    .mrc-comparison input[type='number'] {
        width: 100%;
        border: 1px solid #1f2127;
        background: #1f2127;
        color: #ffffff;
        border-radius: 12px;
        padding: 14px 12px;
        font-size: 15px;
        font-weight: 500;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .mrc-comparison input[type='number']:focus {
        outline: none;
        border-color: #f9d55b;
        box-shadow: 0 0 0 3px rgba(249, 213, 91, 0.25);
        background: #23252d;
    }

    .mrc-comparison input[type='number']::placeholder {
        color: #7e838f;
    }

    .mrc-comparison .mrc-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        background: linear-gradient(135deg, #fbd65b, #f2c83f);
        color: #1e1e21;
        border: none;
        border-radius: 14px;
        padding: 15px 18px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    }

    .mrc-comparison .mrc-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
    }

    .mrc-comparison .mrc-submit:focus-visible {
        outline: 2px solid #f9d55b;
        outline-offset: 3px;
    }

    .mrc-comparison .mrc-error {
        color: #ff8a8a;
        margin: 4px 0 0;
        font-weight: 600;
    }

    .mrc-comparison .mrc-results {
        margin-top: 18px;
        padding: 16px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .mrc-comparison .mrc-results-heading {
        margin: 0 0 8px;
        font-size: 16px;
        font-weight: 700;
        color: #ffffff;
    }

    .mrc-comparison .mrc-summary {
        font-weight: 600;
        color: #fbd65b;
        margin-bottom: 10px;
    }

    .mrc-comparison .mrc-breakdown {
        margin: 0;
        padding-left: 18px;
        color: #d9dde6;
        line-height: 1.5;
    }

    .mrc-comparison .mrc-breakdown li + li {
        margin-top: 6px;
    }
    CSS;

    wp_add_inline_script(MRC_HANDLE, $script);
    wp_add_inline_style(MRC_HANDLE, $style);
}
add_action('wp_enqueue_scripts', 'mrc_register_assets');

/**
 * Output comparison markup shared by shortcode and Elementor widget.
 */
function mrc_render_markup($attributes = []) {
    $defaults = [
        'heading' => 'Mortgage rate comparison',
    ];
    $settings = wp_parse_args($attributes, $defaults);

    wp_enqueue_script(MRC_HANDLE);
    wp_enqueue_style(MRC_HANDLE);

    $uid = uniqid('mrc-');
    $ids = [
        'balance' => $uid . '-balance',
        'term' => $uid . '-term',
        'deal' => $uid . '-deal',
        'current' => $uid . '-current',
        'new' => $uid . '-new',
        'fees' => $uid . '-fees',
    ];

    ob_start();
    ?>
    <div class="mrc-comparison" data-mrc-uid="<?php echo esc_attr($uid); ?>">
        <?php if (!empty($settings['heading'])) : ?>
            <h3 class="mrc-heading"><?php echo esc_html($settings['heading']); ?></h3>
        <?php endif; ?>
        <form class="mrc-comparison-form">
            <div class="mrc-field">
                <label class="mrc-label" for="<?php echo esc_attr($ids['balance']); ?>">Mortgage balance</label>
                <input type="number" id="<?php echo esc_attr($ids['balance']); ?>" data-field="balance" name="mrc-balance" required step="0.01" min="0" placeholder="e.g. 250000" />
            </div>
            <div class="mrc-field">
                <label class="mrc-label" for="<?php echo esc_attr($ids['term']); ?>">Remaining term (years)</label>
                <input type="number" id="<?php echo esc_attr($ids['term']); ?>" data-field="term-years" name="mrc-term-years" required step="0.1" min="1" placeholder="e.g. 25" />
            </div>
            <div class="mrc-field">
                <label class="mrc-label" for="<?php echo esc_attr($ids['deal']); ?>">Deal period to compare (years)</label>
                <input type="number" id="<?php echo esc_attr($ids['deal']); ?>" data-field="deal-years" name="mrc-deal-years" required step="0.1" min="0.1" placeholder="e.g. 2" />
            </div>
            <div class="mrc-field">
                <label class="mrc-label" for="<?php echo esc_attr($ids['current']); ?>">Current interest rate (%)</label>
                <input type="number" id="<?php echo esc_attr($ids['current']); ?>" data-field="current-rate" name="mrc-current-rate" required step="0.01" min="0" placeholder="e.g. 4.5" />
            </div>
            <div class="mrc-field">
                <label class="mrc-label" for="<?php echo esc_attr($ids['new']); ?>">New interest rate (%)</label>
                <input type="number" id="<?php echo esc_attr($ids['new']); ?>" data-field="new-rate" name="mrc-new-rate" required step="0.01" min="0" placeholder="e.g. 3.9" />
            </div>
            <div class="mrc-field">
                <label class="mrc-label" for="<?php echo esc_attr($ids['fees']); ?>">New product fees (optional)</label>
                <input type="number" id="<?php echo esc_attr($ids['fees']); ?>" data-field="fees" name="mrc-fees" step="0.01" min="0" placeholder="e.g. 999" />
            </div>
            <div class="mrc-field">
                <button type="submit" class="mrc-submit">Calculate comparison</button>
            </div>
        </form>
        <p class="mrc-error" role="alert" hidden></p>
        <div class="mrc-results" aria-live="polite" hidden>
            <h4 class="mrc-results-heading">Results</h4>
            <div class="mrc-summary"></div>
            <ul class="mrc-breakdown"></ul>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render comparison form via shortcode.
 *
 * @return string
 */
function mrc_render_comparison_form($atts = []) {
    return mrc_render_markup($atts);
}
add_shortcode('mortgage_rate_compare', 'mrc_render_comparison_form');

/**
 * Elementor widget to expose styling controls.
 */
function mrc_register_elementor_widget($widgets_manager) {
    if (!class_exists('\\Elementor\\Widget_Base')) {
        return;
    }

    class MRC_Elementor_Widget extends \Elementor\Widget_Base {
        public function get_name() {
            return 'mortgage_rate_compare';
        }

        public function get_title() {
            return __('Mortgage Rate Compare', 'mrc');
        }

        public function get_icon() {
            return 'eicon-calculator';
        }

        public function get_categories() {
            return ['general'];
        }

        protected function register_controls() {
            $this->start_controls_section('section_content', [
                'label' => __('Content', 'mrc'),
            ]);

            $this->add_control('heading', [
                'label' => __('Heading', 'mrc'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Mortgage rate comparison', 'mrc'),
                'placeholder' => __('Enter heading text', 'mrc'),
            ]);

            $this->end_controls_section();

            $this->start_controls_section('section_style_container', [
                'label' => __('Container', 'mrc'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);

            $this->add_responsive_control('container_padding', [
                'label' => __('Padding', 'mrc'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .mrc-comparison' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            $this->add_responsive_control('container_margin', [
                'label' => __('Margin', 'mrc'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .mrc-comparison' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            $this->add_group_control(\Elementor\Group_Control_Background::get_type(), [
                'name' => 'container_background',
                'selector' => '{{WRAPPER}} .mrc-comparison',
            ]);

            $this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .mrc-comparison',
            ]);

            $this->add_responsive_control('container_radius', [
                'label' => __('Border Radius', 'mrc'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mrc-comparison' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), [
                'name' => 'container_shadow',
                'selector' => '{{WRAPPER}} .mrc-comparison',
            ]);

            $this->end_controls_section();

            $this->start_controls_section('section_style_heading', [
                'label' => __('Heading', 'mrc'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);

            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'heading_typography',
                'selector' => '{{WRAPPER}} .mrc-heading',
            ]);

            $this->add_control('heading_color', [
                'label' => __('Color', 'mrc'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mrc-heading' => 'color: {{VALUE}};',
                ],
            ]);

            $this->end_controls_section();

            $this->start_controls_section('section_style_labels', [
                'label' => __('Labels', 'mrc'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);

            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .mrc-label',
            ]);

            $this->add_control('label_color', [
                'label' => __('Color', 'mrc'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mrc-label' => 'color: {{VALUE}};',
                ],
            ]);

            $this->end_controls_section();

            $this->start_controls_section('section_style_inputs', [
                'label' => __('Inputs', 'mrc'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);

            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} .mrc-comparison input',
            ]);

            $this->add_control('input_text_color', [
                'label' => __('Text Color', 'mrc'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mrc-comparison input' => 'color: {{VALUE}};',
                ],
            ]);

            $this->add_group_control(\Elementor\Group_Control_Background::get_type(), [
                'name' => 'input_background',
                'selector' => '{{WRAPPER}} .mrc-comparison input',
            ]);

            $this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .mrc-comparison input',
            ]);

            $this->add_responsive_control('input_radius', [
                'label' => __('Border Radius', 'mrc'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mrc-comparison input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            $this->add_responsive_control('input_padding', [
                'label' => __('Padding', 'mrc'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .mrc-comparison input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            $this->end_controls_section();

            $this->start_controls_section('section_style_button', [
                'label' => __('Button', 'mrc'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);

            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .mrc-submit',
            ]);

            $this->add_control('button_text_color', [
                'label' => __('Text Color', 'mrc'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mrc-submit' => 'color: {{VALUE}};',
                ],
            ]);

            $this->add_group_control(\Elementor\Group_Control_Background::get_type(), [
                'name' => 'button_background',
                'selector' => '{{WRAPPER}} .mrc-submit',
            ]);

            $this->add_group_control(\Elementor\Group_Control_Border::get_type(), [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .mrc-submit',
            ]);

            $this->add_responsive_control('button_radius', [
                'label' => __('Border Radius', 'mrc'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .mrc-submit' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            $this->add_responsive_control('button_padding', [
                'label' => __('Padding', 'mrc'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'selectors' => [
                    '{{WRAPPER}} .mrc-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            $this->end_controls_section();

            $this->start_controls_section('section_style_results', [
                'label' => __('Results', 'mrc'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);

            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'results_heading_typography',
                'selector' => '{{WRAPPER}} .mrc-results-heading',
            ]);

            $this->add_control('results_heading_color', [
                'label' => __('Heading Color', 'mrc'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mrc-results-heading' => 'color: {{VALUE}};',
                ],
            ]);

            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'results_text_typography',
                'selector' => '{{WRAPPER}} .mrc-summary, {{WRAPPER}} .mrc-breakdown',
            ]);

            $this->add_control('results_text_color', [
                'label' => __('Text Color', 'mrc'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mrc-summary, {{WRAPPER}} .mrc-breakdown' => 'color: {{VALUE}};',
                ],
            ]);

            $this->add_control('results_spacing', [
                'label' => __('Top Spacing', 'mrc'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'selectors' => [
                    '{{WRAPPER}} .mrc-results' => 'margin-top: {{SIZE}}{{UNIT}};',
                ],
            ]);

            $this->end_controls_section();

            $this->start_controls_section('section_style_error', [
                'label' => __('Error', 'mrc'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]);

            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'error_typography',
                'selector' => '{{WRAPPER}} .mrc-error',
            ]);

            $this->add_control('error_color', [
                'label' => __('Color', 'mrc'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mrc-error' => 'color: {{VALUE}};',
                ],
            ]);

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();
            echo mrc_render_markup([
                'heading' => $settings['heading'],
            ]);
        }
    }

    $widgets_manager->register(new MRC_Elementor_Widget());
}
add_action('elementor/widgets/register', 'mrc_register_elementor_widget');
