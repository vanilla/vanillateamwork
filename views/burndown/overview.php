<?php if ($this->data('burndown')): ?>

    <?php
    $week = $this->data('burndown.startdate');
    ?>

    <div class="burndown overview js-burndown is-burndown is-active-entity" data-burndownid="<?php echo $week; ?>">

        <!-- analytics -->
        <?php echo $this->mustache('_partials/viewburndown'); ?>

        <!-- completion -->
        <?php echo $this->mustache('_partials/viewcompletion'); ?>

        <!-- /burndown -->
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            burndown.start('overview');
        });
    </script>

<?php else: ?>

    <h1>view burndown</h1>
    <div class="context">
        no data
    </div>

<?php endif; ?>
